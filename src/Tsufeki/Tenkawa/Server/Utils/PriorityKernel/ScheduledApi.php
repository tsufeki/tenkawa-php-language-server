<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils\PriorityKernel;

use Recoil\Kernel\Api;
use Recoil\Kernel\Exception\RejectedException;
use Recoil\Kernel\SystemStrand;

class ScheduledApi implements Api
{
    /**
     * @var Api
     */
    private $innerApi;

    /**
     * @var Scheduler
     */
    private $scheduler;

    public function __construct(Api $innerApi, Scheduler $scheduler)
    {
        $this->innerApi = $innerApi;
        $this->scheduler = $scheduler;
    }

    public function __dispatch(SystemStrand $strand, $key, $value)
    {
        if (null === $value) {
            return $this->cooperate($strand);
        }

        if (\is_int($value) || \is_float($value)) {
            return $this->sleep($strand, $value);
        }

        if (\is_array($value)) {
            return $this->all($strand, ...$value);
        }

        if (\is_resource($value)) {
            if (\is_string($key)) {
                return $this->write($strand, $value, $key, PHP_INT_MAX);
            }

            return $this->read($strand, $value, 1, PHP_INT_MAX);
        }

        if (\method_exists($value, 'then')) {
            $onFulfilled = static function ($result) use ($strand) {
                $this->scheduler->scheduleSend($strand, 0.0, $result);
            };

            $onRejected = static function ($reason) use ($strand) {
                if ($reason instanceof \Throwable) {
                    $this->scheduler->scheduleThrow($strand, $reason);
                } else {
                    $this->scheduler->scheduleThrow($strand, new RejectedException($reason));
                }
            };

            if (\method_exists($value, 'done')) {
                $value->done($onFulfilled, $onRejected);
            } else {
                $value->then($onFulfilled, $onRejected);
            }

            if (\method_exists($value, 'cancel')) {
                $strand->setTerminator(function () use ($value) {
                    $value->cancel();
                });
            }

            return null;
        }

        return $this->innerApi->__dispatch($strand, $key, $value);
    }

    public function cooperate(SystemStrand $strand)
    {
        $this->scheduler->scheduleSend($strand);
    }

    public function sleep(SystemStrand $strand, float $interval)
    {
        $this->scheduler->scheduleSend($strand, $interval);
    }

    public function __call(string $name, array $arguments)
    {
        return $this->innerApi->__call($name, $arguments);
    }

    public function execute(SystemStrand $strand, $coroutine)
    {
        return $this->innerApi->execute($strand, $coroutine);
    }

    public function callback(SystemStrand $strand, callable $coroutine)
    {
        return $this->innerApi->callback($strand, $coroutine);
    }

    public function timeout(SystemStrand $strand, float $timeout, $coroutine)
    {
        return $this->innerApi->timeout($strand, $timeout, $coroutine);
    }

    public function strand(SystemStrand $strand)
    {
        return $this->innerApi->strand($strand);
    }

    public function suspend(
        SystemStrand $strand,
        callable $suspendFn = null,
        callable $terminateFn = null
    ) {
        return $this->innerApi->suspend($strand, $suspendFn, $terminateFn);
    }

    public function terminate(SystemStrand $strand)
    {
        return $this->innerApi->terminate($strand);
    }

    public function stop(SystemStrand $strand)
    {
        return $this->innerApi->stop($strand);
    }

    public function link(
        SystemStrand $strand,
        SystemStrand $strandA,
        SystemStrand $strandB = null
    ) {
        return $this->innerApi->link($strand, $strandA, $strandB);
    }

    public function unlink(
        SystemStrand $strand,
        SystemStrand $strandA,
        SystemStrand $strandB = null
    ) {
        return $this->innerApi->unlink($strand, $strandA, $strandB);
    }

    public function adopt(SystemStrand $strand, SystemStrand $substrand)
    {
        return $this->innerApi->adopt($strand, $substrand);
    }

    private function inheritPriority(SystemStrand $strand, array $coroutines): array
    {
        $priority = $strand instanceof PriorityStrand ? $strand->getPriority() : 0;

        return array_map(function ($coroutine) use ($priority) {
            yield Priority::set($priority);

            return yield $coroutine;
        }, $coroutines);
    }

    public function all(SystemStrand $strand, ...$coroutines)
    {
        return $this->innerApi->all($strand, ...$this->inheritPriority($strand, $coroutines));
    }

    public function any(SystemStrand $strand, ...$coroutines)
    {
        return $this->innerApi->any($strand, ...$this->inheritPriority($strand, $coroutines));
    }

    public function some(SystemStrand $strand, int $count, ...$coroutines)
    {
        return $this->innerApi->some($strand, $count, ...$this->inheritPriority($strand, $coroutines));
    }

    public function first(SystemStrand $strand, ...$coroutines)
    {
        return $this->innerApi->first($strand, ...$this->inheritPriority($strand, $coroutines));
    }

    public function read(
        SystemStrand $strand,
        $stream,
        int $minLength,
        int $maxLength
    ) {
        return $this->innerApi->read($strand, $stream, $minLength, $maxLength);
    }

    public function write(
        SystemStrand $strand,
        $stream,
        string $buffer,
        int $length
    ) {
        return $this->innerApi->write($strand, $stream, $buffer, $length);
    }

    public function select(SystemStrand $strand, array $read, array $write)
    {
        return $this->innerApi->select($strand, $read, $write);
    }
}
