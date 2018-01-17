<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Parser\Ast;
use Tsufeki\Tenkawa\Parser\Parser;
use Tsufeki\Tenkawa\Parser\TokenIterator;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Reflection\Element\ClassConst;
use Tsufeki\Tenkawa\Reflection\Element\Element;
use Tsufeki\Tenkawa\Reflection\Element\Method;
use Tsufeki\Tenkawa\Reflection\Element\Property;
use Tsufeki\Tenkawa\TypeInference\BasicType;
use Tsufeki\Tenkawa\TypeInference\IntersectionType;
use Tsufeki\Tenkawa\TypeInference\ObjectType;
use Tsufeki\Tenkawa\TypeInference\Type;
use Tsufeki\Tenkawa\TypeInference\TypeInference;
use Tsufeki\Tenkawa\TypeInference\UnionType;
use Tsufeki\Tenkawa\Utils\PositionUtils;

class MembersHelper
{
    /**
     * @var ClassResolver
     */
    private $classResolver;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var TypeInference
     */
    private $typeInference;

    public function __construct(ClassResolver $classResolver, Parser $parser, TypeInference $typeInference)
    {
        $this->classResolver = $classResolver;
        $this->parser = $parser;
        $this->typeInference = $typeInference;
    }

    /**
     * @return int|null
     */
    private function getMemberNameOffset(Node $node, Node $leftNode, array $tokens, $separatorToken, $nameToken)
    {
        $tokenIndex = $leftNode->getAttribute('endTokenPos') + 1;
        $lastTokenIndex = $node->getAttribute('endTokenPos');
        $tokenOffset = $leftNode->getAttribute('endFilePos') + 1;

        $iterator = new TokenIterator(array_slice($tokens, $tokenIndex, $lastTokenIndex - $tokenIndex + 1), 0, $tokenOffset);
        $iterator->eatWhitespace();
        if (!$iterator->isType($separatorToken)) {
            return null;
        }
        $iterator->eat();
        $iterator->eatWhitespace();
        if (!$iterator->isType($nameToken)) {
            return null;
        }

        return $iterator->getOffset();
    }

    /**
     * @resolve Element[][] member name => member array
     */
    private function getMembersForType(Type $type, string $memberKind, Document $document): \Generator
    {
        if ($type instanceof ObjectType) {
            $resolvedClass = yield $this->classResolver->resolve($type->class, $document);

            return $resolvedClass ? array_map(function ($member) {
                return [$member];
            }, $resolvedClass->$memberKind) : [];
        }

        if ($type instanceof IntersectionType) {
            $members = yield array_map(function (Type $subtype) use ($memberKind, $document) {
                return $this->getMembersForType($subtype, $memberKind, $document);
            }, $type->types);

            return array_merge_recursive(...$members);
        }

        if ($type instanceof UnionType) {
            $subtypes = array_values(array_filter($type->types, function (Type $subtype) {
                return $subtype instanceof ObjectType
                    || $subtype instanceof UnionType
                    || $subtype instanceof IntersectionType;
            }));

            if (empty($subtypes)) {
                return [];
            }

            $members = yield array_map(function (Type $subtype) use ($memberKind, $document) {
                return $this->getMembersForType($subtype, $memberKind, $document);
            }, $subtypes);

            $elements = [];
            foreach (array_keys(count($members) === 1 ? $members[0] : array_intersect_key(...$members)) as $key) {
                $elements[$key] = array_merge(...array_column($members, $key));
            }

            return $elements;
        }

        return [];
    }

    /**
     * @resolve array<string,ClassConst[]>
     */
    public function getClassConstsForType(Type $type, Document $document): \Generator
    {
        return yield $this->getMembersForType($type, 'consts', $document);
    }

    /**
     * @resolve array<string,Property[]>
     */
    public function getPropertiesForType(Type $type, Document $document): \Generator
    {
        return yield $this->getMembersForType($type, 'properties', $document);
    }

    /**
     * @resolve array<string,Method[]>
     */
    public function getMethodsForType(Type $type, Document $document): \Generator
    {
        return yield $this->getMembersForType($type, 'methods', $document);
    }

    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve Element[]
     */
    public function getReflectionFromNodePath(array $nodes, Document $document, Position $position): \Generator
    {
        if (empty($nodes)) {
            return [];
        }

        /** @var Ast $ast */
        $ast = yield $this->parser->parse($document);
        $node = $nodes[0];
        $offset = PositionUtils::offsetFromPosition($position, $document);

        $leftNode = null;
        $name = null;
        $separatorToken = null;
        $nameToken = null;

        if ($node instanceof Expr\ClassConstFetch || $node instanceof Expr\StaticCall) {
            $leftNode = $node->class;
            $name = $node->name;
            $separatorToken = T_PAAMAYIM_NEKUDOTAYIM;
            $nameToken = T_STRING;
        } elseif ($node instanceof Expr\StaticPropertyFetch) {
            $leftNode = $node->class;
            $name = $node->name;
            $separatorToken = T_PAAMAYIM_NEKUDOTAYIM;
            $nameToken = T_VARIABLE;
        } elseif ($node instanceof Expr\PropertyFetch || $node instanceof Expr\MethodCall) {
            $leftNode = $node->var;
            $name = $node->name;
            $separatorToken = T_OBJECT_OPERATOR;
            $nameToken = T_STRING;
        }

        if ($leftNode === null || !is_string($name) || $separatorToken === null || $nameToken === null) {
            return [];
        }

        $nameOffset = $this->getMemberNameOffset($node, $leftNode, $ast->tokens, $separatorToken, $nameToken);
        if ($offset < $nameOffset || $offset >= $nameOffset + strlen($name)) {
            return [];
        }

        yield $this->typeInference->infer($document);
        $type = new BasicType();
        if ($leftNode instanceof Node\Name) {
            $type = new ObjectType();
            $type->class = '\\' . ltrim((string)$leftNode, '\\');
        } elseif ($leftNode instanceof Expr) {
            $type = $leftNode->getAttribute('type', $type);
        }

        $allElements = [];
        if ($node instanceof Expr\ClassConstFetch) {
            $allElements = yield $this->getClassConstsForType($type, $document);
        } elseif ($node instanceof Expr\PropertyFetch || $node instanceof Expr\StaticPropertyFetch) {
            $allElements = yield $this->getPropertiesForType($type, $document);
        } elseif ($node instanceof Expr\MethodCall || $node instanceof Expr\StaticCall) {
            $allElements = yield $this->getMethodsForType($type, $document);
            $name = strtolower($name);
        }

        return $allElements[$name] ?? [];
    }
}
