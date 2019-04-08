<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Symbol;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Php\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassLike;
use Tsufeki\Tenkawa\Php\TypeInference\BasicType;
use Tsufeki\Tenkawa\Php\TypeInference\ObjectType;
use Tsufeki\Tenkawa\Php\TypeInference\Type;
use Tsufeki\Tenkawa\Php\TypeInference\TypeInference;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Utils\Cache;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class MemberSymbolExtractor implements NodePathSymbolExtractor
{
    /**
     * @var ClassResolver
     */
    private $classResolver;

    /**
     * @var TypeInference
     */
    private $typeInference;

    private const NODE_KINDS = [
        Expr\PropertyFetch::class => MemberSymbol::PROPERTY,
        Expr\StaticPropertyFetch::class => MemberSymbol::PROPERTY,
        Expr\MethodCall::class => MemberSymbol::METHOD,
        Expr\StaticCall::class => MemberSymbol::METHOD,
        Expr\ClassConstFetch::class => MemberSymbol::CLASS_CONST,
    ];

    public function __construct(ClassResolver $classResolver, TypeInference $typeInference)
    {
        $this->classResolver = $classResolver;
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
    public function getSymbolAt(Document $document, Position $position, array $nodes, bool $forCompletion): \Generator
    {
        yield $this->typeInference->infer($document, $nodes);
        /** @var MemberSymbol|null $symbol */
        $symbol = yield $this->getSymbolFromNodes($nodes, $document, null, $forCompletion);

        if ($symbol !== null && (PositionUtils::compare($position, $symbol->range->start) < 0 || PositionUtils::compare($position, $symbol->range->end) > 0)) {
            $symbol = null;
        }

        return $symbol;
    }

    /**
     * @param (Node|Comment)[][] $nodes
     *
     * @resolve Symbol[]
     */
    public function getSymbolsInRange(Document $document, Range $range, array $nodes, ?string $symbolClass = null): \Generator
    {
        if ($symbolClass !== null && $symbolClass !== MemberSymbol::class) {
            return [];
        }

        $cache = new Cache();
        yield $this->typeInference->infer($document, null, $cache);

        return array_values(array_filter(yield array_map(function (array $nodes) use ($document, $cache) {
            return $this->getSymbolFromNodes($nodes, $document, $cache);
        }, $nodes)));
    }

    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve MemberSymbol|null
     */
    private function getSymbolFromNodes(array $nodes, Document $document, ?Cache $cache, bool $forCompletion = false): \Generator
    {
        if (empty($nodes)) {
            return null;
        }

        /** @var Expr\PropertyFetch|Expr\StaticPropertyFetch|Expr\MethodCall|Expr\StaticCall|Expr\ClassConstFetch $node */
        $node = $nodes[0];
        $kind = self::NODE_KINDS[get_class($node)];

        $symbol = new MemberSymbol();
        $symbol->kind = $kind;
        $symbol->nameContext = $node->getAttribute('nameContext') ?? new NameContext();
        $symbol->document = $document;
        $symbol->referencedNames = $node->name instanceof Identifier ? [$node->name->name] : [];
        $symbol->range = PositionUtils::rangeFromNodeAttrs($node->name->getAttributes(), $document);

        if ($node instanceof Expr\PropertyFetch || $node instanceof Expr\MethodCall) {
            $symbol->static = false;
            $leftNode = $node->var;
        } else {
            $symbol->static = true;
            $leftNode = $node->class;
            if ($leftNode instanceof Name) {
                $symbol->literalClassName = true;
            }
        }

        $symbol->objectType = yield $this->getTypeFromNode($leftNode, $symbol->nameContext, $document, $cache);
        $symbol->isInObjectContext = $this->isInObjectContext($nodes);

        $completionRange = $this->getCompletionRange($symbol, $leftNode);
        if ($forCompletion && $completionRange !== null) {
            $symbol->range = $completionRange;
            $symbol->kind = $symbol->static ? MemberSymbol::CLASS_CONST : MemberSymbol::PROPERTY;
        }

        return $symbol;
    }

    private function getCompletionRange(MemberSymbol $symbol, Node $leftNode): ?Range
    {
        $middleStart = PositionUtils::rangeFromNodeAttrs($leftNode->getAttributes(), $symbol->document)->end;
        $middleEnd = $symbol->range->start;
        $middleText = PositionUtils::extractRange(new Range($middleStart, $middleEnd), $symbol->document);
        $op = $symbol->static ? '::' : '->';
        if (strlen($middleText) !== strrpos($middleText, $op) + 2) {
            $start = PositionUtils::offsetFromPosition($middleStart, $symbol->document) + strrpos($middleText, $op) + 2;
            $position = PositionUtils::positionFromOffset($start, $symbol->document);

            return new Range($position, $position);
        }

        return null;
    }

    /**
     * @resolve Type
     */
    private function getTypeFromNode(
        Node $node,
        NameContext $nameContext,
        Document $document,
        ?Cache $cache
    ): \Generator {
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
