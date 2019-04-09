<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index\Storage;

use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Uri;

interface WritableIndexStorage extends IndexStorage
{
    /**
     * @param IndexEntry[] $entries
     */
    public function replaceFile(Uri $uri, array $entries, ?string $stamp): \Generator;
}
