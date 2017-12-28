<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Io;

use Tsufeki\Tenkawa\Uri;
use Webmozart\Glob\Glob;
use Webmozart\Glob\Iterator\GlobFilterIterator;
use Webmozart\Glob\Iterator\RecursiveDirectoryIterator;

class LocalFileSearch implements FileSearch
{
    public function searchWithTimestamps(Uri $baseDir, string $pattern): \Generator
    {
        $glob = $baseDir->getFilesystemPath() . '/' . $pattern;
        $basePath = Glob::getBasePath($glob);

        try {
            /** @var iterable<string,\SplFileInfo> $iterator */
            $iterator = new GlobFilterIterator(
                $glob,
                new \RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $basePath,
                        RecursiveDirectoryIterator::KEY_AS_PATHNAME |
                        RecursiveDirectoryIterator::CURRENT_AS_FILEINFO |
                        RecursiveDirectoryIterator::SKIP_DOTS
                    ),
                    \RecursiveIteratorIterator::SELF_FIRST,
                    \RecursiveIteratorIterator::CATCH_GET_CHILD
                ),
                GlobFilterIterator::FILTER_KEY
            );
        } catch (\Exception $e) {
            return [];
        }

        $result = [];
        $yieldEvery = 1000;
        $i = 0;
        foreach ($iterator as $path => $fileInfo) {
            try {
                if (!$fileInfo->isDir()) {
                    $result[(string)Uri::fromFilesystemPath($path)] = $fileInfo->getMTime();
                }
            } catch (\Exception $e) {
            }

            $i++;
            if ($i % $yieldEvery === 0) {
                yield;
            }
        }

        return $result;
    }
}
