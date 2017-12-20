<?php

namespace Tests\Tsufeki\Tenkawa\Utils;

use PHPUnit\Framework\TestCase;
use Evenement\EventEmitter;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Utils\Event;

/**
 * @covers \Tsufeki\Tenkawa\Utils\Event
 */
class EventTest extends TestCase
{
    public function test_first()
    {
        ReactKernel::start(function () {
            $emitter = new EventEmitter();

            list($actual) = yield [
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
