<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Server\Event;

use PHPUnit\Framework\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\HmContainer\Container;
use Tsufeki\Tenkawa\Server\Event\EventDispatcher;

/**
 * @covers \Tsufeki\Tenkawa\Server\Event\EventDispatcher
 */
class EventDispatcherTest extends TestCase
{
    public function test_dispatch()
    {
        ReactKernel::start(function () {
            $listener = new class() {
                public $data;

                public function onEvent($data)
                {
                    $this->data = $data;

                    return;
                    yield;
                }
            };

            $container = $this->createMock(Container::class);
            $container
                ->expects($this->once())
                ->method('getOrDefault')
                ->with($this->identicalTo('OnEvent'), $this->identicalTo([]))
                ->willReturn([$listener]);

            $data = new \stdClass();
            $dispatcher = new EventDispatcher($container);
            yield $dispatcher->dispatch('OnEvent', $data);
            yield;
            yield;
            yield;

            $this->assertSame($data, $listener->data);
        });
    }
}
