<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Language;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Php\Parser\Ast;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Php\Parser\TokenIterator;
use Tsufeki\Tenkawa\Php\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\ResolvedClassLike;
use Tsufeki\Tenkawa\Php\TypeInference\BasicType;
use Tsufeki\Tenkawa\Php\TypeInference\IntersectionType;
use Tsufeki\Tenkawa\Php\TypeInference\ObjectType;
use Tsufeki\Tenkawa\Php\TypeInference\Type;
use Tsufeki\Tenkawa\Php\TypeInference\TypeInference;
use Tsufeki\Tenkawa\Php\TypeInference\UnionType;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Common\Range;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

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
     * @param (Node|Comment)[] $nodes
     *
     * @resolve MemberFetch|null
     */
    public function getMemberFetch(array $nodes, Document $document, Position $position, bool $stickToRightEnd = false): \Generator
    {
        if (($nodes[0] ?? null) instanceof Expr\Error) {
            array_shift($nodes);
        }

        if (empty($nodes)) {
            return null;
        }

        $fetch = new MemberFetch();
        $node = $fetch->node = $nodes[0];
        $separatorToken = null;
        $nameTokens = null;

        if ($node instanceof Expr\ClassConstFetch || $node instanceof Expr\StaticCall) {
            $fetch->leftNode = $node->class;
            $fetch->name = $node->name;
            $separatorToken = T_PAAMAYIM_NEKUDOTAYIM;
            $nameTokens = [T_STRING];
        } elseif ($node instanceof Expr\StaticPropertyFetch) {
            $fetch->leftNode = $node->class;
            $fetch->name = $node->name;
            $separatorToken = T_PAAMAYIM_NEKUDOTAYIM;
            $nameTokens = [T_VARIABLE, '$'];
        } elseif ($node instanceof Expr\PropertyFetch || $node instanceof Expr\MethodCall) {
            $fetch->leftNode = $node->var;
            $fetch->name = $node->name;
            $separatorToken = T_OBJECT_OPERATOR;
            $nameTokens = [T_STRING];
        } else {
            return null;
        }

        if ($fetch->name instanceof Expr) {
            $fetch->nameRange = PositionUtils::rangeFromNodeAttrs($fetch->name->getAttributes(), $document);
            if ($node instanceof Expr\StaticPropertyFetch) {
                $fetch->nameRange->start->character--; // account for '$'
            }

            return $fetch;
        }

        /** @var Ast $ast */
        $ast = yield $this->parser->parse($document);
        $offset = PositionUtils::offsetFromPosition($position, $document);

        $tokenIndex = $fetch->leftNode->getAttribute('endTokenPos') + 1;
        $lastTokenIndex = $node->getAttribute('endTokenPos');
        $tokenOffset = $fetch->leftNode->getAttribute('endFilePos') + 1;

        $iterator = new TokenIterator(array_slice($ast->tokens, $tokenIndex, $lastTokenIndex - $tokenIndex + 1), 0, $tokenOffset);
        $iterator->eatWhitespace();
        if (!$iterator->isType($separatorToken)) {
            return null;
        }
        $iterator->eat();
        $iterator->eatWhitespace();
        if (!$iterator->isType(...$nameTokens)) {
            return null;
        }

        $nameOffset = $iterator->getOffset();
        $nameOffsetEnd = $nameOffset + strlen($iterator->getValue());
        if ($offset < $nameOffset || $offset >= $nameOffsetEnd + (int)$stickToRightEnd) {
            return null;
        }

        $fetch->nameRange = new Range(
            PositionUtils::positionFromOffset($nameOffset, $document),
            PositionUtils::positionFromOffset($nameOffsetEnd, $document)
        );

        return $fetch;
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
    private function getClassConstsForType(Type $type, Document $document): \Generator
    {
        return yield $this->getMembersForType($type, 'consts', $document);
    }

    /**
     * @resolve array<string,Property[]>
     */
    private function getPropertiesForType(Type $type, Document $document): \Generator
    {
        return yield $this->getMembersForType($type, 'properties', $document);
    }

    /**
     * @resolve array<string,Method[]>
     */
    private function getMethodsForType(Type $type, Document $document): \Generator
    {
        return yield $this->getMembersForType($type, 'methods', $document);
    }

    /**
     * @param (ClassConst|Method|Property)[] $members
     * @param NameContext                    $nameContext
     *
     * @resolve (ClassConst|Method|Property)[]
     */
    private function filterAccesibleMembers(array $members, NameContext $nameContext, Document $document): \Generator
    {
        /** @var string[] $parentClassNames */
        $parentClassNames = [];
        if ($nameContext->class !== null) {
            /** @var ResolvedClassLike $resolveClass */
            $resolvedClass = yield $this->classResolver->resolve($nameContext->class, $document);
            while ($resolvedClass !== null) {
                $parentClassNames[] = strtolower($resolvedClass->name);
                $resolvedClass = $resolvedClass->parentClass;
            }
            $parentClassNames = $parentClassNames ?: [strtolower($nameContext->class)];
        }

        return array_values(array_filter($members, function ($element) use ($parentClassNames) {
            switch ($element->accessibility) {
                case ClassLike::M_PUBLIC:
                    return true;
                case ClassLike::M_PROTECTED:
                    return in_array(strtolower($element->nameContext->class), $parentClassNames, true);
                case ClassLike::M_PRIVATE:
                    return strtolower($element->nameContext->class) === ($parentClassNames[0] ?? '');
                default:
                    return false;
            }
        }));
    }

    /**
     * @param (ClassConst|Method|Property)[] $members
     *
     * @return (ClassConst|Method|Property)[]
     */
    private function filterStaticMembers(array $members, bool $static = true): array
    {
        return array_values(array_filter($members, function ($element) use ($static) {
            return $element->static === $static;
        }));
    }

    /**
     * @resolve Element[]
     */
    public function getReflectionFromMemberFetch(MemberFetch $memberFetch, Document $document): \Generator
    {
        if (!is_string($memberFetch->name)) {
            return [];
        }

        $node = $memberFetch->node;
        $leftNode = $memberFetch->leftNode;
        $name = $memberFetch->name;

        /** @var NameContext $nameContext */
        $nameContext = $node->getAttribute('nameContext') ?? new NameContext();
        /** @var Type $type */
        $type = yield $this->getTypeFromNode($leftNode, $nameContext, $document);

        $allElements = [];
        if ($node instanceof Expr\ClassConstFetch) {
            $allElements = yield $this->getClassConstsForType($type, $document);
        } elseif ($node instanceof Expr\PropertyFetch || $node instanceof Expr\StaticPropertyFetch) {
            $allElements = yield $this->getPropertiesForType($type, $document);
        } elseif ($node instanceof Expr\MethodCall || $node instanceof Expr\StaticCall) {
            $allElements = yield $this->getMethodsForType($type, $document);
            $name = strtolower($name);
        }

        return yield $this->filterAccesibleMembers($allElements[$name] ?? [], $nameContext, $document);
    }

    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve Element[]
     */
    public function getAllReflectionsFromMemberFetch(MemberFetch $memberFetch, array $nodes, Document $document): \Generator
    {
        $node = $memberFetch->node;
        $leftNode = $memberFetch->leftNode;
        $name = $memberFetch->name;

        /** @var NameContext $nameContext */
        $nameContext = $node->getAttribute('nameContext') ?? new NameContext();
        /** @var Type $type */
        $type = yield $this->getTypeFromNode($leftNode, $nameContext, $document);

        $consts = $this->flatten(yield $this->getClassConstsForType($type, $document));
        $properties = $this->flatten(yield $this->getPropertiesForType($type, $document));
        $methods = $this->flatten(yield $this->getMethodsForType($type, $document));

        /** @var Element[][] $allElements */
        $allElements = [];
        if ($node instanceof Expr\ClassConstFetch) {
            $allElements[] = $consts;
            $allElements[] = $this->filterStaticMembers($methods, true);
            $allElements[] = $this->filterStaticMembers($properties, true);

            if (yield $this->isStaticCallToNonStaticAllowed($leftNode, $nodes, $nameContext, $document)) {
                $allElements[] = $this->filterStaticMembers($methods, false);
            }

            if ($leftNode instanceof Name) {
                $classConst = new ClassConst();
                $classConst->name = 'class';
                $classConst->nameContext = new NameContext();
                $classConst->nameContext->class = '\\' . (string)$leftNode;
                $classConst->accessibility = ClassLike::M_PUBLIC;
                $classConst->static = true;
                $allElements[] = [$classConst];
            }
        } elseif ($node instanceof Expr\StaticPropertyFetch) {
            $allElements[] = $this->filterStaticMembers($properties, true);
        } elseif ($node instanceof Expr\StaticCall) {
            $allElements[] = $this->filterStaticMembers($methods, true);

            if (yield $this->isStaticCallToNonStaticAllowed($leftNode, $nodes, $nameContext, $document)) {
                $allElements[] = $this->filterStaticMembers($methods, false);
            }
        } elseif ($node instanceof Expr\PropertyFetch) {
            $allElements[] = $this->filterStaticMembers($properties, false);
            $allElements[] = $methods;
        } elseif ($node instanceof Expr\MethodCall) {
            $allElements[] = $methods;
        }

        $elements = yield $this->filterAccesibleMembers(array_merge(...$allElements), $nameContext, $document);

        if (!($leftNode instanceof Name) || strtolower((string)$leftNode) !== 'parent') {
            $elements = array_values(array_filter($elements, function (Element $element) {
                return !($element instanceof Method) || !in_array(strtolower($element->name), ['__construct', '__destruct'], true);
            }));
        }

        return $elements;
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

    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve bool
     */
    private function isStaticCallToNonStaticAllowed(
        Node $className,
        array $nodes,
        NameContext $nameContext,
        Document $document
    ): \Generator {
        if ($nameContext->class === null
            || !$this->isInObjectContext($nodes)
            || !($className instanceof Name)
        ) {
            return false;
        }

        if (in_array(strtolower((string)$className), ['self', 'static', 'parent'], true)) {
            return true;
        }

        /** @var ResolvedClassLike|null $class */
        $class = yield $this->classResolver->resolve($nameContext->class, $document);
        while ($class !== null) {
            if (strtolower($class->name) === '\\' . strtolower((string)$className)) {
                return true;
            }
            $class = $class->parentClass;
        }

        return false;
    }

    /**
     * @param array<string,(ClassConst|Method|Property)[]> $elements
     *
     * @return (ClassConst|Method|Property)[]
     */
    private function flatten(array $elements): array
    {
        return empty($elements) ? [] : array_merge(...array_values($elements));
    }
}
