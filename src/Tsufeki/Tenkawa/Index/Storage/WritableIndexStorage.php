<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index\Storage;

use Tsufeki\Tenkawa\Uri;

interface WritableIndexStorage extends IndexStorage
{
    /**
     * @param IndexEntry[] $entries
     */
    public function replaceFile(Uri $uri, array $entries, int $timestamp = null): \Generator;
}
