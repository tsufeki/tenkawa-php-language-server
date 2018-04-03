<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Server\Utils;

use PHPStan\Testing\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Server\Utils\SyncAsyncKernel;

/**
 * @covers \Tsufeki\Tenkawa\Server\Utils\SyncAsyncKernel
 * @covers \Tsufeki\Tenkawa\Server\Utils\SyncCallContext
 */
class SyncAsyncKernelTest extends TestCase
{
    public function test()
    {
        $sa = new SyncAsyncKernel([ReactKernel::class, 'create']);

        $sa->start(function () use ($sa) {
            $result = $sa->callSync(function () use ($sa) {
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
