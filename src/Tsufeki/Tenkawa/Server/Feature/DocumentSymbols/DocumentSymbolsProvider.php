<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\DocumentSymbols;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\SymbolInformation;

interface DocumentSymbolsProvider
{
    /**
     * @resolve SymbolInformation[]
     */
    public function getSymbols(Document $document): \Generator;
}
