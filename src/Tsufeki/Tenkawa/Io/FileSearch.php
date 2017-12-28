<?php

namespace Tsufeki\Tenkawa\Io;

use Tsufeki\Tenkawa\Uri;

interface FileSearch
{
    /**
     * @param string $pattern Glob pattern, like "**\/*.php"
     *
     * @resolve Uri[]
     */
    public function search(Uri $baseDir, string $pattern): \Generator;
}