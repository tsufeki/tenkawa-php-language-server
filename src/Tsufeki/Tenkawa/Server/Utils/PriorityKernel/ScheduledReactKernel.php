<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils\PriorityKernel;

use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Recoil\Kernel\Api;
use Recoil\Kernel\KernelState;
use Recoil\Kernel\KernelTrait;
use Recoil\Kernel\SystemKernel;
use Recoil\React\ReactApi;
use Recoil\Strand;
use SplQueue;

class ScheduledReactKernel implements SystemKernel
{
    use KernelTrait;

    /**
     * @var LoopInterface
     */
    private $eventLoop;

    /**
     * @var Scheduler
     */
    private $scheduler;

    /**
     * @var int The next strand ID.
     */
    private $nextId = 1;

    private function __construct(LoopInterface $eventLoop, Api $api, Scheduler $scheduler)
    {
        $this->eventLoop = $eventLoop;
        $this->api = $api;
        $this->scheduler = $scheduler;
        $this->panicExceptions = new SplQueue();
    }

    public static function create(?LoopInterface $eventLoop = null, ?Scheduler $scheduler = null): self
    {
        if ($eventLoop === null) {
            $eventLoop = Factory::create();
        }

        if ($scheduler === null) {
            $scheduler = new ReactScheduler($eventLoop);
        }

        return new self(
            $eventLoop,
            new ScheduledApi(new ReactApi($eventLoop), $scheduler),
            $scheduler
        );
    }

    public function execute($coroutine): Strand
    {
        $strand = new PriorityStrand($this, $this->api, $this->nextId++, $coroutine);
        $this->scheduler->scheduleStart($strand);

        return $strand;
    }

    public function stop()
    {
        /** @var int $state */
        $state = &$this->state; // To override wrong annotation in KernelTrait
        if ($state === KernelState::RUNNING) {
            $state = KernelState::STOPPING;
            $this->eventLoop->stop();
        }
    }

    protected function loop()
    {
        $this->eventLoop->run();
    }
}
