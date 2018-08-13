<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server;

use Tsufeki\HmContainer\Container;

abstract class Plugin
{
    /**
     * Add plugin's services to the DI container.
     *
     * This method must not call `$container->get()` or otherwize freeze the
     * container.
     */
    public function configureContainer(Container $container, array $options): void
    {
    }
}
