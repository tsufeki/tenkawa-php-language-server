<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io\FileLister;

use Tsufeki\Tenkawa\Server\Uri;

interface FileLister
{
    /**
     * @param FileFilter[] $filters
     *
     * @resolve \Iterator string $uri => [string $fileType, int $mtime]
     */
    public function list(Uri $uri, array $filters): \Generator;
}