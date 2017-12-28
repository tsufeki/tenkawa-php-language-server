<?php

namespace Tsufeki\Tenkawa\Io;

use Tsufeki\Tenkawa\Uri;
use Webmozart\Glob\Glob;

class LocalFileSearch implements FileSearch
{
    public function search(Uri $baseDir, string $pattern): \Generator
    {
        return array_map(function ($path) {
            return Uri::fromFilesystemPath($path);
        }, Glob::glob($baseDir->getFilesystemPath() . '/' . $pattern));
        yield;
    }
}