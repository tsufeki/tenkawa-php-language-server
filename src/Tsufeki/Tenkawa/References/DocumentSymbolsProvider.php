<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\SymbolInformation;

interface DocumentSymbolsProvider
{
    /**
     * @resolve SymbolInformation[]
     */
    public function getSymbols(Document $document): \Generator;
}
