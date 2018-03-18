<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

use Recoil\Kernel;
use Recoil\Strand;

class SyncAsyncKernel implements Kernel
{
    /**
     * @var callable () -> Kernel
     */
    private $kernelFactory;

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
    private $syncStack = [];

    public function __construct(callable $kernelFactory)
    {
        $this->kernelFactory = $kernelFactory;
        $this->kernel = $kernelFactory();
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

        $context = new SyncCallContext();
        $context->resumeCallback = $resumeCallback;
        $context->pauseCallback = $pauseCallback;

        $this->sync = true;
        $this->syncStack[] = $context;
        $context->resume();

        try {
            $result = $syncCallable(...$args);
        } finally {
            $this->sync = false;
            $context->pause();
            array_pop($this->syncStack);
        }

        return $result;
        yield;
    }

    /**
     * @return mixed
     */
    public function callAsync(\Generator $coroutine)
    {
        if (!$this->sync) {
            throw new \RuntimeException("Can't call SyncAsyncKernel::callAsync in async mode");
        }

        $oldKernel = $this->kernel;
        $this->kernel = ($this->kernelFactory)();

        $this->sync = false;
        $context = !empty($this->syncStack) ? $this->syncStack[count($this->syncStack) - 1] : null;
        if ($context) {
            $context->pause();
        }

        try {
            $result = $this->kernel->start($coroutine);
        } finally {
            $this->sync = true;
            if ($context) {
                $context->resume();
            }
            $this->kernel = $oldKernel;
        }

        return $result;
    }

    public function run()
    {
        if (!$this->sync) {
            throw new \RuntimeException("Can't call SyncAsyncKernel::run in async mode");
        }

        $this->sync = false;
        $this->kernel->run();
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
