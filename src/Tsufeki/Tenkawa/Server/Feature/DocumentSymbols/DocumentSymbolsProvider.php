<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\DocumentSymbols;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\DocumentSymbolClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Common\SymbolInformation;

interface DocumentSymbolsProvider
{
    /**
     * @resolve DocumentSymbol[]|SymbolInformation[]
     */
    public function getSymbols(Document $document, DocumentSymbolClientCapabilities $capabilities): \Generator;
}
