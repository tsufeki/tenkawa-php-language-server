<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa;

class PluginFinder
{
    /**
     * @return Plugin[]
     */
    public function findPlugins(): array
    {
        return [new CorePlugin()];
    }
}
