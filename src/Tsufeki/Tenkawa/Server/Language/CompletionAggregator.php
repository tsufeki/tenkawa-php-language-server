<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionContext;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionList;

class CompletionAggregator
{
    /**
     * @var CompletionProvider[]
     */
    private $providers;

    /**
     * @param CompletionProvider[] $providers
     */
    public function __construct(array $providers)
    {
        $this->providers = $providers;
    }

    /**
     * @resolve CompletionList
     */
    public function getCompletions(
        Document $document,
        Position $position,
        CompletionContext $context = null
    ): \Generator {
        $completions = new CompletionList();

        $completionsLists = yield array_map(function (CompletionProvider $provider) use ($document, $position, $context) {
            return $provider->getCompletions($document, $position, $context);
        }, $this->providers);

        $completions->items = array_merge(...array_map(function (CompletionList $list) {
            return $list->items;
        }, $completionsLists));

        $completions->isIncomplete = 0 !== array_sum(array_map(function (CompletionList $list) {
            return $list->isIncomplete;
        }, $completionsLists));

        return $completions;
    }

    /**
     * @return string[]
     */
    public function getTriggerCharacters(): array
    {
        return array_unique(array_merge(...array_map(function (CompletionProvider $provider) {
            return $provider->getTriggerCharacters();
        }, $this->providers)));
    }

    public function hasProviders(): bool
    {
        return !empty($this->providers);
    }
}
