<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\NodeTraverser;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Parser\FindNodeVisitor;
use Tsufeki\Tenkawa\Parser\Parser;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionContext;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionList;

class CompletionAggregator
{
    /**
     * @var CompletionProvider[]
     */
    private $providers;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @param CompletionProvider[] $providers
     */
    public function __construct(array $providers, Parser $parser)
    {
        $this->providers = $providers;
        $this->parser = $parser;
    }

    /**
     * @resolve CompletionList
     */
    public function getCompletions(
        Document $document,
        Position $position,
        CompletionContext $context = null
    ): \Generator {
        $ast = yield $this->parser->parse($document);

        $visitor = new FindNodeVisitor($document, $position, true);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);
        $nodes = $visitor->getNodes();

        $visitor = new NameContextTaggingVisitor($nodes);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);

        $completions = new CompletionList();

        $completionsLists = yield array_map(function (CompletionProvider $provider) use ($document, $position, $context, $nodes) {
            return $provider->getCompletions($document, $position, $context, $nodes);
        }, $this->providers);

        $completions->items = array_merge(...array_map(function (CompletionList $list) {
            return $list->items;
        }, $completionsLists));

        $completions->isIncomplete = 0 !== array_sum(array_map(function (CompletionList $list) {
            return $list->isIncomplete;
        }, $completionsLists));

        return $completions;
    }

    public function hasProviders(): bool
    {
        return !empty($this->providers);
    }
}
