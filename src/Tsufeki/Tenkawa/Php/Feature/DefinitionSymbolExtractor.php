<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class DefinitionSymbolExtractor implements NodePathSymbolExtractor
{
    private const NODE_KINDS = [
        Const_::class => null,
        Stmt\Function_::class => GlobalSymbol::FUNCTION_,
        Stmt\Class_::class => GlobalSymbol::CLASS_,
        Stmt\Interface_::class => GlobalSymbol::CLASS_,
        Stmt\Trait_::class => GlobalSymbol::CLASS_,
        Stmt\PropertyProperty::class => MemberSymbol::PROPERTY,
        Stmt\ClassMethod::class => MemberSymbol::METHOD,
    ];

    /**
     * @param Node|Comment $node
     */
    public function filterNode($node): bool
    {
        return array_key_exists(get_class($node), self::NODE_KINDS);
    }

    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve Symbol|null
     */
    public function getSymbolAt(Document $document, Position $position, array $nodes, bool $forCompletion): \Generator
    {
        /** @var DefinitionSymbol|null $symbol */
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
    public function getSymbolsInRange(Document $document, Range $range, array $nodes, ?string $symbolClass = null): \Generator
    {
        if ($symbolClass !== null && $symbolClass !== DefinitionSymbol::class) {
            return [];
        }

        return array_values(array_filter(yield array_map(function (array $nodes) use ($document) {
            return $this->getSymbolFromNodes($nodes, $document);
        }, $nodes)));
    }

    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve DefinitionSymbol|null
     */
    private function getSymbolFromNodes(array $nodes, Document $document): \Generator
    {
        $node = $nodes[0] ?? null;
        if (!($node instanceof Node)) {
            return null;
        }

        $name = null;
        $range = null;
        if (isset($node->name)) {
            assert($node->name instanceof Identifier);
            $name = $node->name->name;
            $range = PositionUtils::rangeFromNodeAttrs($node->name->getAttributes(), $document);
        }
        if (isset($node->namespacedName)) {
            $name = '\\' . (string)$node->namespacedName;
        }

        $kind = self::NODE_KINDS[get_class($node)] ?? null;
        if ($node instanceof Const_ && isset($nodes[1])) {
            $kind = ($nodes[1] instanceof Stmt\ClassConst) ? MemberSymbol::CLASS_CONST : GlobalSymbol::CONST_;
        }

        if ($name === null || $kind === null || $range === null) {
            return null;
        }

        $symbol = new DefinitionSymbol();
        $symbol->referencedNames = [$name];
        $symbol->kind = $kind;
        $symbol->document = $document;
        $symbol->nameContext = $node->getAttribute('nameContext') ?? new NameContext();
        $symbol->range = $range;
        $symbol->definitionRange = PositionUtils::rangeFromNodeAttrs($node->getAttributes(), $document);

        return $symbol;
        yield;
    }
}
