<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Index\Storage\WritableIndexStorage;

class Indexer
{
    /**
     * @var IndexDataProvider[]
     */
    private $indexDataProviders;

    /**
     * @param IndexDataProvider[] $indexDataProviders
     */
    public function __construct(array $indexDataProviders)
    {
        $this->indexDataProviders = $indexDataProviders;
    }

    public function indexDocument(Document $document, WritableIndexStorage $indexStorage, int $timestamp = null): \Generator
    {
        $entries = [];
        foreach ($this->indexDataProviders as $provider) {
            $entries = array_merge($entries, yield $provider->getEntries($document));
        }

        yield $indexStorage->replaceFile($document->getUri(), $entries, $timestamp);
    }
}
