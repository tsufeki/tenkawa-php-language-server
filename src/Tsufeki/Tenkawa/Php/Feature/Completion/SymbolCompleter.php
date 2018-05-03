<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItem;

interface SymbolCompleter
{
    /**
     * @resolve CompletionItem[]
     */
    public function getCompletions(Symbol $symbol, Position $position): \Generator;

    /**
     * @return string[]
     */
    public function getTriggerCharacters(): array;
}
