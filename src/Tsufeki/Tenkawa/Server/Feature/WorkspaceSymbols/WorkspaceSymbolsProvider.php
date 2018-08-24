<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\WorkspaceSymbols;

use Tsufeki\Tenkawa\Server\Feature\Common\SymbolInformation;

interface WorkspaceSymbolsProvider
{
    /**
     * @resolve SymbolInformation[]
     */
    public function getSymbols(string $query): \Generator;
}
