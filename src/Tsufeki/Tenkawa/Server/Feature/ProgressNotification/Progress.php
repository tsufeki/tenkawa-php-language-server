<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\ProgressNotification;

class Progress
{
    /**
     * @var ProgressGroup
     */
    private $progressGroup;

    /**
     * @var bool
     */
    private $done = false;

    public function __construct(ProgressGroup $progressGroup)
    {
        $this->progressGroup = $progressGroup;
    }

    public function __destruct()
    {
        $this->done();
    }

    public function set(string $label, ?int $status = null): void
    {
        $this->progressGroup->progress($label, $status);
    }

    public function done(): void
    {
        if (!$this->done) {
            $this->done = true;
            $this->progressGroup->done();
        }
    }
}
