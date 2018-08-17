<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

use Tsufeki\Tenkawa\Server\Index\Storage\IndexStorage;

interface GlobalIndexer
{
    /**
     * @resolve IndexStorage
     */
    public function getIndex(): \Generator;

    public function buildIndex(Indexer $indexer): \Generator;
}
