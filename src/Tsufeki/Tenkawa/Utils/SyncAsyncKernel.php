<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Utils;

use Recoil\Kernel;
use Recoil\Recoil;
use Recoil\Strand;

class SyncAsyncKernel implements Kernel
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
        if ($this->sync) {
            throw new \RuntimeException("Can't call SyncAsyncKernel::callSync in sync mode");
        }

        $strand = yield Recoil::strand();
        $context = new SyncCallContext();
        $context->resumeCallback = $resumeCallback;
        $context->pauseCallback = $pauseCallback;

        $context->callable = function () use ($syncCallable, $args, $strand) {
            try {
                $strand->send($syncCallable(...$args));
            } catch (\Throwable $e) {
                $strand->throw($e);
            }
        };

        $result = yield Recoil::suspend(function () use ($context) {
            $this->syncNext[] = $context;
            $this->kernel->stop();
        });

        return $result;
    }

    /**
     * @return mixed
     */
    public function callAsync(\Generator $coroutine)
    {
        if (!$this->sync) {
            throw new \RuntimeException("Can't call SyncAsyncKernel::callAsync in async mode");
        }

        $done = false;
        $result = null;
        $exception = null;

        $this->kernel->execute(function () use ($coroutine, &$done, &$result, &$exception) {
            try {
                $result = yield $coroutine;
            } catch (\Throwable $e) {
                $exception = $e;
            }

            $done = true;
            yield Recoil::execute(Recoil::stop());
        });

        while (!$done) {
            $this->run();
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    public function run()
    {
        if (!$this->sync) {
            throw new \RuntimeException("Can't call SyncAsyncKernel::run in async mode");
        }

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

    public function stop()
    {
        $this->kernel->stop();
    }

    public function execute($coroutine): Strand
    {
        return $this->kernel->execute($coroutine);
    }

    public function setExceptionHandler(callable $fn = null)
    {
        $this->kernel->setExceptionHandler($fn);
    }

    public function send($value = null, Strand $strand = null)
    {
        $this->kernel->send($value, $strand);
    }

    public function throw(\Throwable $exception, Strand $strand = null)
    {
        $this->kernel->throw($exception, $strand);
    }

    public function start($coroutine)
    {
        $this->execute($coroutine);
        $this->run();
    }
}
