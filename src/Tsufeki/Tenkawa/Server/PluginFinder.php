<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server;

use Tsufeki\Tenkawa\Php\PhpPlugin;

class PluginFinder
{
    /**
     * @return Plugin[]
     */
    public function findPlugins(): array
    {
        return [new ServerPlugin(), new PhpPlugin()];
    }
}
