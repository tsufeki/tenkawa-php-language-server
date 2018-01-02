<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Index\Storage\IndexStorage;

class Index
{
    const PREFIX = IndexStorage::PREFIX;
    const SUFFIX = IndexStorage::SUFFIX;
    const FULL = IndexStorage::FULL;

    /**
     * @resolve IndexEntry[]
     */
    public function search(Document $document, string $category = null, string $key, int $match = self::FULL): \Generator
    {
        /** @var IndexStorage $indexStorage */
        $indexStorage = $document->getProject()->get('index');

        return yield $indexStorage->search($category, $key, $match);
    }
}
