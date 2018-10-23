<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\ProgressNotification;

class ProgressGroup
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
     * @var int
     */
    private $activeCount = 0;

    public function __construct(string $id, callable $progressCallback)
    {
        $this->id = $id;
        $this->progressCallback = $progressCallback;
    }

    public function get(): Progress
    {
        $this->activeCount++;

        return new Progress($this);
    }

    /**
     * @internal
     */
    public function progress(string $label, ?int $status = null): void
    {
        ($this->progressCallback)($this->id, $label, $status);
    }

    /**
     * @internal
     */
    public function done(): void
    {
        $this->activeCount--;
        if ($this->activeCount <= 0) {
            ($this->progressCallback)($this->id, null, null, true);
        }
    }
}
