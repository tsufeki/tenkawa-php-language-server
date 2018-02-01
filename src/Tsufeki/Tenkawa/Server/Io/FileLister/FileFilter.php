<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io\FileLister;

interface FileFilter
{
    const ACCEPT = 1;
    const REJECT = -1;
    const ABSTAIN = 0;

    public function filter(string $uri, string $baseUri): int;

    public function getFileType(): string;

    public function enterDirectory(string $uri, string $baseUri): int;
}
