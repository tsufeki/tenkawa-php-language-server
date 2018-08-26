<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

use Recoil\Recoil;
use Recoil\Strand;

class Throttler
{
    /**
     * @var int
     */
    private $maxConcurrentJobs;

    /**
     * @var int
     */
    private $inProgressJobs = 0;

    /**
     * @var \SplQueue
     */
    private $queue;

    public function __construct(int $maxConcurrentJobs)
    {
        $this->maxConcurrentJobs = $maxConcurrentJobs;
        $this->queue = new \SplQueue();
    }

    /**
     * @param mixed $job Coroutine to run.
     */
    public function run($job): \Generator
    {
        while ($this->inProgressJobs >= $this->maxConcurrentJobs) {
            $strand = yield Recoil::strand();
            $this->queue->enqueue($strand);
            yield Recoil::suspend();
        }

        $result = null;
        $this->inProgressJobs++;

        try {
            $result = yield $job;
        } finally {
            $this->inProgressJobs--;
            if (!$this->queue->isEmpty()) {
                /** @var Strand $next */
                $next = $this->queue->dequeue();
                yield Recoil::execute(function () use ($next): \Generator {
                    $next->send();

                    return;
                    yield;
                });
            }
        }

        return $result;
    }
}
