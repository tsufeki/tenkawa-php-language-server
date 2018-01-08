<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index;

use Tsufeki\Tenkawa\Index\Storage\WritableIndexStorage;

interface GlobalIndexer
{
    public function index(WritableIndexStorage $globalIndexStorage, Indexer $indexer): \Generator;
}
