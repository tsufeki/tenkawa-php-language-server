<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils\PriorityKernel;

use React\EventLoop\LoopInterface;
use Recoil\Kernel\SystemStrand;
use Recoil\Strand;

class ReactScheduler implements Scheduler
{
    /**
     * @var LoopInterface
     */
    private $eventLoop;

    /**
     * @var \SplPriorityQueue
     */
    private $callbacks;

    /**
     * @var int
     */
    private $serial = 0;

    public function __construct(LoopInterface $eventLoop)
    {
        $this->eventLoop = $eventLoop;
        $this->callbacks = new \SplPriorityQueue();
    }

    public function scheduleStart(SystemStrand $strand, float $delay = 0.0): void
    {
        $this->schedule($strand, $delay, function () use ($strand) {
            assert(method_exists($strand, 'start'));
            if (!$strand->hasExited()) {
                $strand->start();
            }
        });
    }

    public function scheduleSend(SystemStrand $strand, float $delay = 0.0, $value = null): void
    {
        $this->schedule($strand, $delay, function () use ($strand, $value) {
            if (!$strand->hasExited()) {
                $strand->send($value);
            }
        });
    }

    private function schedule(SystemStrand $strand, float $delay, callable $callback): void
    {
        $priority = $this->getPriority($strand);

        if ($delay <= 0.0) {
            $this->callbacks->insert($callback, [$priority, -(++$this->serial)]);
            $this->run();
        } else {
            $timer = $this->eventLoop->addTimer($delay, function () use ($callback, $priority) {
                $this->callbacks->insert($callback, [$priority, -(++$this->serial)]);
                $this->run();
            });
            $strand->setTerminator(function () use ($timer) {
                $this->eventLoop->cancelTimer($timer);
            });
        }
    }

    private function getPriority(Strand $strand): int
    {
        return $strand instanceof PriorityStrand ? $strand->getPriority() : 0;
    }

    private function run(): void
    {
        $this->eventLoop->futureTick(function () {
            if ($this->callbacks->isEmpty()) {
                return;
            }

            $callback = $this->callbacks->extract();
            $callback();

            if (!$this->callbacks->isEmpty()) {
                $this->run();
            }
        });
    }
}
