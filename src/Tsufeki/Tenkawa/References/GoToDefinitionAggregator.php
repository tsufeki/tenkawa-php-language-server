<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\NodeTraverser;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Parser\FindNodeVisitor;
use Tsufeki\Tenkawa\Parser\Parser;
use Tsufeki\Tenkawa\Protocol\Common\Position;

class GoToDefinitionAggregator
{
    /**
     * @var GoToDefinitionProvider[]
     */
    private $providers;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @param GoToDefinitionProvider[] $providers
     */
    public function __construct(array $providers, Parser $parser)
    {
        $this->providers = $providers;
        $this->parser = $parser;
    }

    /**
     * @resolve Location[]
     */
    public function getLocations(Document $document, Position $position): \Generator
    {
        $ast = yield $this->parser->parse($document);

        $visitor = new FindNodeVisitor($document, $position);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);
        $nodes = $visitor->getNodes();

        $visitor = new NameContextTaggingVisitor($nodes);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);

        return array_merge(
            ...yield array_map(function (GoToDefinitionProvider $provider) use ($document, $position, $nodes) {
                return $provider->getLocations($document, $position, $nodes);
            }, $this->providers)
        );
    }

    public function hasProviders(): bool
    {
        return !empty($this->providers);
    }
}
