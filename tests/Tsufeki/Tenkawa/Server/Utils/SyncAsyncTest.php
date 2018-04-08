<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Server\Utils;

use PHPStan\Testing\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Server\Utils\NestedKernelsSyncAsync;

/**
 * @covers \Tsufeki\Tenkawa\Server\Utils\NestedKernelsSyncAsyncKernel
 * @covers \Tsufeki\Tenkawa\Server\Utils\SyncCallContext
 */
class SyncAsyncTest extends TestCase
{
    public function test()
    {
        $kernel = ReactKernel::create();
        $sa = new NestedKernelsSyncAsync([ReactKernel::class, 'create']);

        $kernel->execute(function () use ($sa) {
            $result = $sa->callSync(function () use ($sa) {
                return 'foo' . $sa->callAsync((function () {
                    yield;

                    return 'bar';
                })());
            });
            yield;

            $this->assertSame('foobar', $result);
        });
        $kernel->run();
    }
}
