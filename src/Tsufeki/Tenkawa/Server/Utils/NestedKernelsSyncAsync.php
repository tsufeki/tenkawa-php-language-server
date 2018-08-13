<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

use Recoil\Kernel;

class NestedKernelsSyncAsync implements SyncAsync
{
    /**
     * @var callable () -> Kernel
     */
    private $kernelFactory;

    /**
     * @var Kernel|null Cached nested kernel.
     */
    private $cachedKernel;

    /**
     * @var SyncCallContext[]
     */
    private $syncStack = [];

    public function __construct(callable $kernelFactory)
    {
        $this->kernelFactory = $kernelFactory;
    }

    public function callSync(
        callable $syncCallable,
        array $args = [],
        ?callable $resumeCallback = null,
        ?callable $pauseCallback = null
    ) {
        $context = new SyncCallContext();
        $context->resumeCallback = $resumeCallback;
        $context->pauseCallback = $pauseCallback;

        $this->syncStack[] = $context;
        $context->resume();

        try {
            $result = $syncCallable(...$args);
        } finally {
            $context->pause();
            array_pop($this->syncStack);
        }

        return $result;
    }

    public function callAsync(\Generator $coroutine)
    {
        assert(!empty($this->syncStack));
        $context = $this->syncStack[count($this->syncStack) - 1];
        $context->pause();

        $kernel = $this->cachedKernel ?? ($this->kernelFactory)();
        $this->cachedKernel = null;

        try {
            $result = $kernel->start($coroutine);
        } finally {
            $context->resume();
            $this->cachedKernel = $kernel;
        }

        return $result;
    }
}
