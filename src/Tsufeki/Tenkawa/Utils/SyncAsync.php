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
     * @var callable|null
     */
    private $syncCallable;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public function callSync(callable $syncCallable, ...$args): \Generator
    {
        assert(!$this->sync);

        $done = false;
        $result = null;
        $exception = null;

        $this->syncCallable = function () use ($syncCallable, $args, &$done, &$result, &$exception) {
            try {
                $result = $syncCallable(...$args);
            } catch (\Throwable $e) {
                $exception = $e;
            } finally {
                $done = true;
            }
        };

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

        while (true) {
            $this->sync = false;

            try {
                $this->kernel->run();
            } finally {
                $this->sync = true;
            }

            if ($this->syncCallable !== null) {
                $syncCallable = $this->syncCallable;
                $this->syncCallable = null;
                $syncCallable();
            } else {
                break;
            }
        }
    }

    public function start($coroutine)
    {
        $this->kernel->execute($coroutine);
        $this->run();
    }
}
