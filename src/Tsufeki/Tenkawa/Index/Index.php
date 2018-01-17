<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Index\Storage\IndexStorage;

class Index
{
    /**
     * @resolve IndexEntry[]
     */
    public function search(Document $document, Query $query): \Generator
    {
        /** @var IndexStorage $indexStorage */
        $indexStorage = $document->getProject()->get('index');

        return yield $indexStorage->search($query);
    }
}
