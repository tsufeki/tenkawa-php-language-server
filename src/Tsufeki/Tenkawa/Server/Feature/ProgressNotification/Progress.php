<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\ProgressNotification;

class Progress
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var callable
     */
    private $progressCallback;

    /**
     * @var bool
     */
    private $started = false;

    /**
     * @var bool
     */
    private $done = false;

    public function __construct(string $id, callable $progressCallback)
    {
        $this->id = $id;
        $this->progressCallback = $progressCallback;
    }

    public function __destruct()
    {
        $this->done();
    }

    public function set(string $label, ?int $status = null): void
    {
        if ($status !== null || !$this->started) {
            $this->started = true;
            ($this->progressCallback)($this->id, $label, $status);
        }
    }

    public function done(): void
    {
        if ($this->started && !$this->done) {
            $this->done = true;
            ($this->progressCallback)($this->id, null, null, true);
        }
    }
}
