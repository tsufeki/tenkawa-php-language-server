<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Php\Parser\Ast;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Php\Parser\TokenIterator;
use Tsufeki\Tenkawa\Php\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\ResolvedClassLike;
use Tsufeki\Tenkawa\Php\TypeInference\BasicType;
use Tsufeki\Tenkawa\Php\TypeInference\ObjectType;
use Tsufeki\Tenkawa\Php\TypeInference\Type;
use Tsufeki\Tenkawa\Php\TypeInference\TypeInference;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class MemberSymbolExtractor implements NodePathSymbolExtractor
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

    const NODE_KINDS = [
        Expr\PropertyFetch::class => MemberSymbol::PROPERTY,
        Expr\StaticPropertyFetch::class => MemberSymbol::PROPERTY,
        Expr\MethodCall::class => MemberSymbol::METHOD,
        Expr\StaticCall::class => MemberSymbol::METHOD,
        Expr\ClassConstFetch::class => MemberSymbol::CLASS_CONST,
    ];

    const STATICS = [
        Expr\StaticPropertyFetch::class => true,
        Expr\StaticCall::class => true,
        Expr\ClassConstFetch::class => true,
    ];

    const SEPARATOR_TOKENS = [
        Expr\PropertyFetch::class => T_OBJECT_OPERATOR,
        Expr\StaticPropertyFetch::class => T_PAAMAYIM_NEKUDOTAYIM,
        Expr\MethodCall::class => T_OBJECT_OPERATOR,
        Expr\StaticCall::class => T_PAAMAYIM_NEKUDOTAYIM,
        Expr\ClassConstFetch::class => T_PAAMAYIM_NEKUDOTAYIM,
    ];

    const NAME_TOKENS = [
        Expr\PropertyFetch::class => [T_STRING],
        Expr\StaticPropertyFetch::class => [T_VARIABLE, '$'],
        Expr\MethodCall::class => [T_STRING],
        Expr\StaticCall::class => [T_STRING],
        Expr\ClassConstFetch::class => [T_STRING],
    ];

    public function __construct(ClassResolver $classResolver, Parser $parser, TypeInference $typeInference)
    {
        $this->classResolver = $classResolver;
        $this->parser = $parser;
        $this->typeInference = $typeInference;
    }

    /**
     * @param Node|Comment $node
     */
    public function filterNode($node): bool
    {
        return isset(self::NODE_KINDS[get_class($node)]);
    }

    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve Symbol|null
     */
    public function getSymbolAt(Document $document, Position $position, array $nodes): \Generator
    {
        /** @var MemberSymbol|null $symbol */
        $symbol = yield $this->getSymbolFromNodes($nodes, $document);

        if ($symbol !== null) {
            $offset = PositionUtils::offsetFromPosition($position, $document);
            $start = PositionUtils::offsetFromPosition($symbol->range->start, $document);
            $end = PositionUtils::offsetFromPosition($symbol->range->end, $document);

            if ($offset < $start || $offset > $end) {
                $symbol = null;
            }
        }

        return $symbol;
    }

    /**
     * @param (Node|Comment)[][] $nodes
     *
     * @resolve Symbol[]
     */
    public function getSymbolsInRange(Document $document, Range $range, array $nodes): \Generator
    {
        return array_values(array_filter(yield array_map(function (array $nodes) use ($document) {
            return $this->getSymbolFromNodes($nodes, $document);
        }, $nodes)));
    }

    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve MemberSymbol|null
     */
    private function getSymbolFromNodes(array $nodes, Document $document): \Generator
    {
        if (($nodes[0] ?? null) instanceof Expr\Error) {
            array_shift($nodes);
        }

        if (empty($nodes)) {
            return null;
        }

        $node = $nodes[0];
        $kind = self::NODE_KINDS[get_class($node)];

        $symbol = new MemberSymbol();
        $symbol->kind = $kind;
        $symbol->static = self::STATICS[get_class($node)] ?? false;
        $symbol->nameContext = $node->getAttribute('nameContext') ?? new NameContext();
        $symbol->document = $document;

        $name = $node->name;
        if ($name instanceof Node) {
            $symbol->referencedNames = [];
            $symbol->range = PositionUtils::rangeFromNodeAttrs($name->getAttributes(), $document);
            if ($node instanceof Expr\StaticPropertyFetch) {
                // account for '$' TODO: there may be whitespace or {}
                $symbol->range->start->character--;
            }
        } else {
            $symbol->referencedNames = [(string)$name];
            /** @var Range|null $range */
            $range = yield $this->getNameRange($node, $document);
            if ($range === null) {
                return null;
            }
            $symbol->range = $range;
        }

        if (!$symbol->static) {
            $leftNode = $node->var;
        } else {
            $leftNode = $node->class;
            if ($leftNode instanceof Name) {
                $symbol->literalClassName = true;
            }
        }
        $symbol->objectType = yield $this->getTypeFromNode($leftNode, $symbol->nameContext, $document);
        $symbol->isInObjectContext = $this->isInObjectContext($nodes);

        return $symbol;
    }

    /**
     * @param Expr\PropertyFetch|Expr\StaticPropertyFetch|Expr\MethodCall|Expr\StaticCall|Expr\ClassConstFetch $node
     *
     * @resolve Range|null
     */
    private function getNameRange(Node $node, Document $document): \Generator
    {
        if ($node instanceof Expr\PropertyFetch || $node instanceof Expr\MethodCall) {
            $leftNode = $node->var;
        } else {
            $leftNode = $node->class;
        }

        /** @var Ast $ast */
        $ast = yield $this->parser->parse($document);

        $tokenIndex = $leftNode->getAttribute('endTokenPos') + 1;
        $lastTokenIndex = $node->getAttribute('endTokenPos');
        $tokenOffset = $leftNode->getAttribute('endFilePos') + 1;

        $iterator = new TokenIterator(array_slice($ast->tokens, $tokenIndex, $lastTokenIndex - $tokenIndex + 1), 0, $tokenOffset);
        $iterator->eatWhitespace();
        if (!$iterator->isType(self::SEPARATOR_TOKENS[get_class($node)])) {
            return null;
        }
        $iterator->eat();
        $iterator->eatWhitespace();
        if (!$iterator->isType(...self::NAME_TOKENS[get_class($node)])) {
            return null;
        }

        return new Range(
            PositionUtils::positionFromOffset($iterator->getOffset(), $document),
            PositionUtils::positionFromOffset($iterator->getOffset() + strlen($iterator->getValue()), $document)
        );
    }

    /**
     * @resolve Type
     */
    private function getTypeFromNode(Node $node, NameContext $nameContext, Document $document): \Generator
    {
        yield $this->typeInference->infer($document);

        $type = new BasicType();
        if ($node instanceof Node\Name) {
            $type = new ObjectType();
            $type->class = '\\' . ltrim((string)$node, '\\');
            if ($nameContext->class !== null) {
                if (in_array(strtolower((string)$node), ['self', 'static'], true)) {
                    $type->class = $nameContext->class;
                } elseif (strtolower((string)$node) === 'parent') {
                    /** @var ResolvedClassLike|null $class */
                    $class = yield $this->classResolver->resolve($nameContext->class, $document);
                    if ($class !== null && $class->parentClass !== null) {
                        $type->class = $class->parentClass->name;
                    }
                }
            }
        } elseif ($node instanceof Expr) {
            $type = $node->getAttribute('type', $type);
        }

        return $type;
    }

    /**
     * @param (Node|Comment)[] $nodes
     */
    public function isInObjectContext(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node instanceof Stmt\ClassMethod) {
                return !$node->isStatic();
            }
            if ($node instanceof Stmt\Function_) {
                return false;
            }
            if ($node instanceof Stmt\ClassLike) {
                return false;
            }
            if ($node instanceof Expr\Closure && $node->static) {
                return false;
            }
        }

        return false;
    }
}
