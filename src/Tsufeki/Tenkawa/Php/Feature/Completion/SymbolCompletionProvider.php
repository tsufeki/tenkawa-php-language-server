<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolExtractor;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionContext;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItem;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionList;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionProvider;

class SymbolCompletionProvider implements CompletionProvider
{
    /**
     * @var SymbolExtractor
     */
    public $symbolExtractor;

    /**
     * @var SymbolCompleter[]
     */
    public $symbolCompleters;

    /**
     * @param SymbolCompleter[] $symbolCompleters
     */
    public function __construct(
        SymbolExtractor $symbolExtractor,
        array $symbolCompleters
    ) {
        $this->symbolExtractor = $symbolExtractor;
        $this->symbolCompleters = $symbolCompleters;
    }

    public function getTriggerCharacters(): array
    {
        return array_merge(...array_map(function (SymbolCompleter $completer) {
            return $completer->getTriggerCharacters();
        }, $this->symbolCompleters));
    }

    public function getCompletions(
        Document $document,
        Position $position,
        ?CompletionContext $context
    ): \Generator {
        if ($document->getLanguage() !== 'php') {
            return new CompletionList();
        }

        /** @var Symbol|null */
        $symbol = yield $this->symbolExtractor->getSymbolAt($document, $position, true);
        if ($symbol === null) {
            return new CompletionList();
        }

        /** @var CompletionItem[] $items */
        $items = array_merge(...yield array_map(function (SymbolCompleter $completer) use ($symbol, $position) {
            return $completer->getCompletions($symbol, $position);
        }, $this->symbolCompleters));

        $completions = new CompletionList();
        $completions->items = $items;

        return $completions;
    }
}
