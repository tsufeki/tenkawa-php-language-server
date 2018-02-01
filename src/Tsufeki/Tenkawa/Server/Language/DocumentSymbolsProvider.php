<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\SymbolInformation;

interface DocumentSymbolsProvider
{
    /**
     * @resolve SymbolInformation[]
     */
    public function getSymbols(Document $document): \Generator;
}
