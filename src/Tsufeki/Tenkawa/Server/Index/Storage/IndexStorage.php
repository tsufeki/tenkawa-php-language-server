<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index\Storage;

use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;
use Tsufeki\Tenkawa\Server\Uri;

interface IndexStorage
{
    /**
     * @resolve IndexEntry[]
     */
    public function search(Query $query): \Generator;

    /**
     * @param Uri $filterUri Filter results to this file/directory.
     *
     * @resolve array<string,int|null> string URI => int timestamp
     */
    public function getFileTimestamps(?Uri $filterUri = null): \Generator;
}
