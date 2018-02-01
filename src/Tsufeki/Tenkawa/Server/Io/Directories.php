<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io;

use Webmozart\PathUtil\Path;

class Directories
{
    public function getCacheDir(): string
    {
        return (getenv('XDG_CACHE_HOME') ?: Path::getHomeDirectory() . '/.cache') . '/tenkawa-php';
    }
}
