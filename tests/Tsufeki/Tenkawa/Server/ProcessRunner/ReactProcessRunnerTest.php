<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Server\ProcessRunner;

use PHPUnit\Framework\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Server\ProcessRunner\ProcessResult;
use Tsufeki\Tenkawa\Server\ProcessRunner\ReactProcessRunner;

/**
 * @covers \Tsufeki\Tenkawa\Server\ProcessRunner\ReactProcessRunner
 */
class ReactProcessRunnerTest extends TestCase
{
    public function test_success()
    {
        ReactKernel::start(function () {
            $runner = new ReactProcessRunner();
            /** @var ProcessResult $result */
            $result = yield $runner->run(['/bin/echo', 'foo']);

            $this->assertSame("foo\n", $result->stdout);
            $this->assertSame('', $result->stderr);
            $this->assertSame(0, $result->exitCode);
            $this->assertNull($result->signal);
        });
    }

    public function test_failure()
    {
        ReactKernel::start(function () {
            $runner = new ReactProcessRunner();
            /** @var ProcessResult $result */
            $result = yield $runner->run(['/bin/grep', 'foo'], 'bar');

            $this->assertSame('', $result->stdout);
            $this->assertSame('', $result->stderr);
            $this->assertSame(1, $result->exitCode);
            $this->assertNull($result->signal);
        });
    }
}
