<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io\FileLister;

use Tsufeki\Tenkawa\Server\Uri;

class LocalFileLister implements FileLister
{
    public function list(Uri $uri, array $filters, ?Uri $baseUri = null): \Generator
    {
        $baseUri = $baseUri ?? $uri;
        $path = $uri->getFilesystemPath();

        if (!file_exists($path)) {
            return new \ArrayIterator([]);
        }

        if (!is_dir($path)) {
            $uriString = $uri->getNormalized();
            [$accept, $fileType] = $this->voteOnAcceptFile($uriString, $filters, $baseUri->getNormalized());
            if ($accept) {
                return new \ArrayIterator([$uriString => [$fileType, filemtime($path)]]);
            }

            return new \ArrayIterator([]);
        }

        try {
            $iterator = new \RecursiveDirectoryIterator(
                $uri->getFilesystemPath(),
                \RecursiveDirectoryIterator::SKIP_DOTS |
                \RecursiveDirectoryIterator::CURRENT_AS_FILEINFO |
                \RecursiveDirectoryIterator::KEY_AS_PATHNAME
            );
        } catch (\Exception $e) {
            return new \ArrayIterator([]);
        }

        return $this->iterate($iterator, $filters, $baseUri->getNormalized());
        yield;
    }

    /**
     * @param \RecursiveDirectoryIterator&iterable<string,\SplFileInfo> $iterator
     * @param FileFilter[]                                              $filters
     */
    private function iterate(\RecursiveDirectoryIterator $iterator, array $filters, string $baseUri): \Iterator
    {
        try {
            foreach ($iterator as $path => $info) {
                $uri = Uri::fromFilesystemPath($path)->getNormalized();
                if ($info->isDir()) {
                    if ($this->voteOnEnterDirectory($uri, $filters, $baseUri) && $iterator->hasChildren()) {
                        yield from $this->iterate($iterator->getChildren(), $filters, $baseUri);
                    }
                } else {
                    [$accept, $fileType] = $this->voteOnAcceptFile($uri, $filters, $baseUri);
                    if ($accept) {
                        yield $uri => [$fileType, $info->getMTime()];
                    }
                }
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * @param FileFilter[] $filters
     */
    private function voteOnEnterDirectory(string $uri, array $filters, string $baseUri): bool
    {
        $accept = false;
        foreach ($filters as $filter) {
            $vote = $filter->enterDirectory($uri, $baseUri);
            if ($vote === FileFilter::ACCEPT) {
                $accept = true;
            } elseif ($vote === FileFilter::REJECT) {
                $accept = false;
                break;
            }
        }

        return $accept;
    }

    /**
     * @param FileFilter[] $filters
     *
     * @return array [string $fileType, int $mtime]
     */
    private function voteOnAcceptFile(string $uri, array $filters, string $baseUri): array
    {
        $accept = false;
        $fileType = null;
        foreach ($filters as $filter) {
            $vote = $filter->filter($uri, $baseUri);
            if ($vote === FileFilter::ACCEPT) {
                $accept = true;
                $fileType = $filter->getFileType();
            } elseif ($vote === FileFilter::REJECT) {
                $accept = false;
                $fileType = null;
                break;
            }
        }

        return [$accept, $fileType];
    }
}
