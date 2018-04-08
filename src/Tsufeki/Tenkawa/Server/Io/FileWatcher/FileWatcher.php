<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io\FileWatcher;

use Tsufeki\Tenkawa\Server\Uri;

interface FileWatcher
{
    public function isAvailable(): bool;

    public function start(): \Generator;

    public function stop(): \Generator;

    public function addDirectory(Uri $uri): \Generator;

    public function removeDirectory(Uri $uri): \Generator;
}
