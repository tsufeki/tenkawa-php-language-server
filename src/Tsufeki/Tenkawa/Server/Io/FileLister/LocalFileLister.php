<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io\FileLister;

use Tsufeki\Tenkawa\Server\Uri;

class LocalFileLister implements FileLister
{
    public function list(Uri $uri, array $filters, ?Uri $baseUri = null): \Generator
    {
        clearstatcache();

        $baseUri = $baseUri ?? $uri;
        $info = new \SplFileInfo($uri->getFilesystemPath());

        try {
            if ($info->isFile()) {
                /** @var \RecursiveArrayIterator<string,\SplFileInfo> */
                $iterator = new \RecursiveArrayIterator(
                    [$uri->getFilesystemPath() => $info],
                    \RecursiveArrayIterator::CHILD_ARRAYS_ONLY
                );
            } elseif ($info->isDir()) {
                /** @var \RecursiveDirectoryIterator<string,\SplFileInfo> */
                $iterator = new \RecursiveDirectoryIterator(
                    $uri->getFilesystemPath(),
                    \RecursiveDirectoryIterator::SKIP_DOTS |
                    \RecursiveDirectoryIterator::CURRENT_AS_FILEINFO |
                    \RecursiveDirectoryIterator::KEY_AS_PATHNAME
                );
            } else {
                /** @var \RecursiveArrayIterator<string,\SplFileInfo> */
                $iterator = new \RecursiveArrayIterator([]);
            }

            return $this->iterate($iterator, $filters, $baseUri->getNormalized());
        } catch (\Exception $e) {
        }

        return new \ArrayIterator([]);
        yield;
    }

    /**
     * @param \RecursiveIterator<string,\SplFileInfo> $iterator
     * @param FileFilter[]                            $filters
     */
    private function iterate(\RecursiveIterator $iterator, array $filters, string $baseUri): \Iterator
    {
        foreach ($iterator as $path => $info) {
            try {
                $uri = Uri::fromFilesystemPath($path)->getNormalized();
                if ($info->isDir()) {
                    if ($this->voteOnEnterDirectory($uri, $filters, $baseUri) && $iterator->hasChildren()) {
                        /** @var \RecursiveDirectoryIterator<string,\SplFileInfo> $children */
                        $children = $iterator->getChildren();
                        yield from $this->iterate($children, $filters, $baseUri);
                    }
                } else {
                    [$accept, $fileType] = $this->voteOnAcceptFile($uri, $filters, $baseUri);
                    if ($accept) {
                        yield $uri => [$fileType, $this->getStamp($info)];
                    }
                }
            } catch (\Exception $e) {
            }
        }
    }

    private function getStamp(\SplFileInfo $info): string
    {
        return $info->getMTime() . '-' . $info->getSize();
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
     * @return array [bool $accept, string $fileType]
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
