<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io;

use Tsufeki\Tenkawa\Server\Uri;

interface FileSearch
{
    /**
     * @param string $pattern Glob pattern, like "**\/*.php"
     *
     * @resolve array<string,int|null> URI string => last modified timestamp
     */
    public function searchWithTimestamps(Uri $baseDir, string $pattern, string $blacklistPattern = null): \Generator;
}
