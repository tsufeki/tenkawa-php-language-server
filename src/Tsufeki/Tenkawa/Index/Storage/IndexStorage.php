<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index\Storage;

use Tsufeki\Tenkawa\Index\IndexEntry;
use Tsufeki\Tenkawa\Index\Query;
use Tsufeki\Tenkawa\Uri;

interface IndexStorage
{
    /**
     * @resolve IndexEntry[]
     */
    public function search(Query $query): \Generator;

    /**
     * @resolve array<string,int|null> string URI => int timestamp
     */
    public function getFileTimestamps(): \Generator;
}
