<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use Tsufeki\Tenkawa\Php\Symbol\Symbol;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionList;

interface SymbolCompleter
{
    /**
     * @resolve CompletionList
     */
    public function getCompletions(Symbol $symbol, Position $position): \Generator;

    /**
     * @return string[]
     */
    public function getTriggerCharacters(): array;
}
