<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;

class SymbolExtractor
{
    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    /**
     * @var NodePathSymbolExtractor[]
     */
    private $nodePathSymbolExtractors;

    private const IGNORED_LEAF_NODES = [
        Expr\Error::class => true,
        Node\Identifier::class => true,
        Node\VarLikeIdentifier::class => true,
    ];

    /**
     * @param NodePathSymbolExtractor[] $nodePathSymbolExtractors
     */
    public function __construct(NodeFinder $nodeFinder, array $nodePathSymbolExtractors)
    {
        $this->nodeFinder = $nodeFinder;
        $this->nodePathSymbolExtractors = $nodePathSymbolExtractors;
    }

    /**
     * @resolve Symbol|null
     */
    public function getSymbolAt(Document $document, Position $position, bool $forCompletion = false): \Generator
    {
        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath($document, $position);
        $firstNode = $nodes[0] ?? null;
        if ($firstNode !== null && isset(self::IGNORED_LEAF_NODES[get_class($firstNode)])) {
            array_shift($nodes);
            $firstNode = $nodes[0] ?? null;
        }
        if ($firstNode === null) {
            return null;
        }

        foreach ($this->nodePathSymbolExtractors as $nodePathSymbolExtractor) {
            if ($nodePathSymbolExtractor->filterNode($firstNode)) {
                $symbol = yield $nodePathSymbolExtractor->getSymbolAt($document, $position, $nodes, $forCompletion);
                if ($symbol !== null) {
                    return $symbol;
                }
            }
        }

        return null;
    }

    /**
     * @resolve Symbol[]
     */
    public function getSymbolsInRange(Document $document, Range $range, ?string $symbolClass = null): \Generator
    {
        $symbols = [];

        foreach ($this->nodePathSymbolExtractors as $nodePathSymbolExtractor) {
            /** @var (Node|Comment)[][] $nodes */
            $nodes = yield $this->nodeFinder->getNodePathsIntersectingWithRange(
                $document,
                $range,
                [$nodePathSymbolExtractor, 'filterNode']
            );

            $symbols = array_merge($symbols, yield $nodePathSymbolExtractor->getSymbolsInRange(
                $document,
                $range,
                $nodes,
                $symbolClass
            ));
        }

        return $symbols;
    }
}
