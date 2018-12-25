<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server;

use Tsufeki\Tenkawa\Phony\PhonyPlugin;
use Tsufeki\Tenkawa\Php\PhpPlugin;
use Tsufeki\Tenkawa\PhpUnit\PhpUnitPlugin;

class PluginFinder
{
    /**
     * @return Plugin[]
     */
    public function findPlugins(): array
    {
        return [
            new ServerPlugin(),
            new PhpPlugin(),
            new PhpUnitPlugin(),
            new PhonyPlugin(),
        ];
    }
}
