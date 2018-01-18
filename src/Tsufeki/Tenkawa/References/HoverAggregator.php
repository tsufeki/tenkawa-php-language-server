<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\NodeTraverser;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Parser\FindNodeVisitor;
use Tsufeki\Tenkawa\Parser\Parser;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\Hover;

class HoverAggregator
{
    /**
     * @var HoverProvider[]
     */
    private $providers;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @param HoverProvider[] $providers
     */
    public function __construct(array $providers, Parser $parser)
    {
        $this->providers = $providers;
        $this->parser = $parser;
    }

    /**
     * @resolve Hover|null
     */
    public function getHover(Document $document, Position $position): \Generator
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

        foreach ($this->providers as $provider) {
            $hover = yield $provider->getHover($document, $position, $nodes);
            if ($hover !== null) {
                return $hover;
            }
        }

        return null;
    }

    public function hasProviders(): bool
    {
        return !empty($this->providers);
    }
}
