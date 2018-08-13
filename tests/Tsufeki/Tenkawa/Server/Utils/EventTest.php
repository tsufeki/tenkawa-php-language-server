<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Server\Utils;

use Evenement\EventEmitter;
use PHPUnit\Framework\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Server\Utils\Event;

/**
 * @covers \Tsufeki\Tenkawa\Server\Utils\Event
 */
class EventTest extends TestCase
{
    public function test_first()
    {
        ReactKernel::start(function () {
            $emitter = new EventEmitter();

            [$actual] = yield [
                Event::first($emitter, 'evt'),
                (function () use ($emitter) {
                    yield;
                    $emitter->emit('evt', [42]);
                })(),
            ];

            $this->assertSame([42], $actual);
        });
    }

    public function test_first_error()
    {
        $this->expectException(\RuntimeException::class);

        ReactKernel::start(function () {
            $emitter = new EventEmitter();

            yield [
                Event::first($emitter, [], ['err']),
                (function () use ($emitter) {
                    yield;
                    $emitter->emit('err', [new \RuntimeException()]);
                })(),
            ];
        });
    }
}
