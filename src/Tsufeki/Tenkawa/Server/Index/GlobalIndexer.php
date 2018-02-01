<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

use Tsufeki\Tenkawa\Server\Index\Storage\WritableIndexStorage;

interface GlobalIndexer
{
    public function index(WritableIndexStorage $globalIndexStorage, Indexer $indexer): \Generator;
}
