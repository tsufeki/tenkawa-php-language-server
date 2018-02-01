<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

use Recoil\Recoil;
use Recoil\Strand;

class Throttler
{
    /**
     * @var int
     */
    private $limit;

    /**
     * @var int
     */
    private $running;

    /**
     * @var \SplQueue
     */
    private $queue;

    public function __construct(int $limit)
    {
        $this->limit = $limit;
        $this->running = 0;
        $this->queue = new \SplQueue();
    }

    public function run(\Generator $task): \Generator
    {
        yield Recoil::execute($this->tick());

        Recoil::suspend(function (Strand $strand) {
            $this->queue->enqueue($strand);
        });

        $this->running++;

        try {
            return yield $task;
        } finally {
            $this->running--;
            yield Recoil::execute($this->tick());
        }
    }

    private function tick(): \Generator
    {
        if ($this->running < $this->limit && $this->queue->count() > 0) {
            /** @var Strand $strand */
            $strand = $this->queue->dequeue();
            $strand->send();
        }

        return;
        yield;
    }
}
