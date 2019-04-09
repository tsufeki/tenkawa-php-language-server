<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io\FileLister;

use Tsufeki\Tenkawa\Server\Uri;

interface FileLister
{
    /**
     * @param FileFilter[] $filters
     * @param Uri|null     $baseUri Base directory, passed to filters.
     *
     * @resolve \Iterator string $uri => [string $fileType, string $stamp]
     */
    public function list(Uri $uri, array $filters, ?Uri $baseUri = null): \Generator;
}
