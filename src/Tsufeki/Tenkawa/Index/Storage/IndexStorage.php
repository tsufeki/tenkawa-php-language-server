<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index\Storage;

use Tsufeki\Tenkawa\Uri;

interface IndexStorage
{
    const PREFIX = 1;
    const SUFFIX = 2;
    const FULL = 3;

    /**
     * @resolve IndexEntry[]
     */
    public function search(string $category = null, string $key, int $match = self::FULL): \Generator;

    public function add(IndexEntry $entry): \Generator;

    public function purgeFile(Uri $uri): \Generator;

    public function setFileTimestamp(Uri $uri, int $timestamp = null): \Generator;

    /**
     * @resolve array<string,int|null> string URI => int timestamp
     */
    public function getFileTimestamps(): \Generator;
}
