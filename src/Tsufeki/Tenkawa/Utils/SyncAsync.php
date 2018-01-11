<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Utils;

use Recoil\Kernel;
use Recoil\Recoil;

class SyncAsync
{
    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var bool
     */
    private $sync = true;

    /**
     * @var SyncCallContext[]
     */
    private $syncNext = [];

    /**
     * @var SyncCallContext[]
     */
    private $syncStack = [];

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public function callSync(
        callable $syncCallable,
        array $args = [],
        callable $resumeCallback = null,
        callable $pauseCallback = null
    ): \Generator {
        assert(!$this->sync);

        $done = false;
        $result = null;
        $exception = null;

        $context = new SyncCallContext();
        $context->resumeCallback = $resumeCallback;
        $context->pauseCallback = $pauseCallback;

        $context->callable = function () use ($syncCallable, $args, &$done, &$result, &$exception) {
            try {
                $result = $syncCallable(...$args);
            } catch (\Throwable $e) {
                $exception = $e;
            } finally {
                $done = true;
            }
        };

        $this->syncNext[] = $context;
        yield Recoil::execute(Recoil::stop());

        while (!$done) {
            yield;
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function callAsync(\Generator $coroutine)
    {
        assert($this->sync);

        $done = false;
        $result = null;
        $exception = null;

        $this->kernel->execute(function () use ($coroutine, &$done, &$result, &$exception) {
            try {
                $result = yield $coroutine;
            } catch (\Throwable $e) {
                $exception = $e;
            } finally {
                $done = true;
                yield Recoil::execute(Recoil::stop());
            }
        });

        while (!$done) {
            $this->run();
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    private function run()
    {
        assert($this->sync);
        $callerContext = null;
        if (!empty($this->syncStack)) {
            $callerContext = $this->syncStack[count($this->syncStack) - 1];
            $callerContext->pause();
        }

        while (true) {
            $this->sync = false;

            try {
                $this->kernel->run();
            } finally {
                $this->sync = true;
            }

            if (!empty($this->syncNext)) {
                $context = array_shift($this->syncNext);
                $this->syncStack[] = $context;
                $context->resume();
                ($context->callable)();
                $context->pause();
                array_pop($this->syncStack);
            } else {
                break;
            }
        }

        if ($callerContext !== null) {
            $callerContext->resume();
        }
    }

    public function start($coroutine)
    {
        $this->kernel->execute($coroutine);
        $this->run();
    }
}
