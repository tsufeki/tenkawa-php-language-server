<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\SymbolInformation;

class DocumentSymbolsAggregator
{
    /**
     * @var DocumentSymbolsProvider[]
     */
    private $providers;

    /**
     * @param DocumentSymbolsProvider[] $providers
     */
    public function __construct(array $providers)
    {
        $this->providers = $providers;
    }

    /**
     * @resolve SymbolInformation[]
     */
    public function getSymbols(Document $document): \Generator
    {
        return array_merge(
            ...yield array_map(function (DocumentSymbolsProvider $provider) use ($document) {
                return $provider->getSymbols($document);
            }, $this->providers)
        );
    }

    public function hasProviders(): bool
    {
        return !empty($this->providers);
    }
}
