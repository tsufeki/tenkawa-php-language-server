<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;

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
    public function getSymbolAt(Document $document, Position $position): \Generator
    {
        $nodes = yield $this->nodeFinder->getNodePath($document, $position);

        foreach ($this->nodePathSymbolExtractors as $nodePathSymbolExtractor) {
            $symbol = yield $nodePathSymbolExtractor->getSymbolAt($document, $position, $nodes);
            if ($symbol !== null) {
                return $symbol;
            }
        }

        return null;
    }
}
