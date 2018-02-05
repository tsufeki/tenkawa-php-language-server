<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Event;

use Recoil\Recoil;
use Tsufeki\HmContainer\Container;

class EventDispatcher
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var float
     */
    private $timeout;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->timeout = 30.0;
    }

    /**
     * Dispatch coroutines in a new strand and return immediately.
     */
    public function dispatch(string $event, ...$args): \Generator
    {
        yield Recoil::execute(Recoil::timeout($this->timeout, $this->dispatchAndWait($event, ...$args)));
    }

    /**
     * Dispatch and wait until all dispatched coroutines finish.
     */
    public function dispatchAndWait(string $event, ...$args): \Generator
    {
        $parts = explode('\\', $event);
        $method = end($parts);
        $listeners = $this->container->getOrDefault($event, []);

        yield array_map(function ($listener) use ($method, $args) {
            return [$listener, $method](...$args);
        }, $listeners);
    }
}
