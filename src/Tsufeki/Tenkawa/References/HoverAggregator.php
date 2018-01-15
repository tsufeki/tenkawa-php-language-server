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
    private $hoverProviders;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @param HoverProvider[] $hoverProviders
     */
    public function __construct(array $hoverProviders, Parser $parser)
    {
        $this->hoverProviders = $hoverProviders;
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

        foreach ($this->hoverProviders as $provider) {
            $hover = yield $provider->getHover($document, $position, $nodes);
            if ($hover !== null) {
                return $hover;
            }
        }

        return null;
    }

    public function hasProviders(): bool
    {
        return !empty($this->hoverProviders);
    }
}