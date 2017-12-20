<?php

namespace Tests\Tsufeki\Tenkawa\ProcessRunner;

use PHPUnit\Framework\TestCase;
use Tsufeki\Tenkawa\ProcessRunner\ReactProcessRunner;
use Tsufeki\Tenkawa\ProcessRunner\ProcessResult;
use Recoil\React\ReactKernel;

/**
 * @covers \Tsufeki\Tenkawa\ProcessRunner\ReactProcessRunner
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
