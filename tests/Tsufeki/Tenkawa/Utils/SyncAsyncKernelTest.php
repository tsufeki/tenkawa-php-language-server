<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Utils;

use PHPStan\Testing\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Utils\SyncAsyncKernel;

/**
 * @covers \Tsufeki\Tenkawa\Utils\SyncAsyncKernel
 * @covers \Tsufeki\Tenkawa\Utils\SyncCallContext
 */
class SyncAsyncKernelTest extends TestCase
{
    public function test()
    {
        $sa = new SyncAsyncKernel(ReactKernel::create());

        $sa->start(function () use ($sa) {
            $result = yield $sa->callSync(function () use ($sa) {
                return 'foo' . $sa->callAsync((function () {
                    yield;

                    return 'bar';
                })());
            });
            yield;

            $this->assertSame('foobar', $result);
        });
    }
}
