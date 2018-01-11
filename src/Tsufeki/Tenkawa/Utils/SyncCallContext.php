<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Utils;

class SyncCallContext
{
    /**
     * @var callable
     */
    public $callable;

    /**
     * @var callable|null
     */
    public $resumeCallback;

    /**
     * @var callable|null
     */
    public $pauseCallback;

    public function resume()
    {
        if ($this->resumeCallback !== null) {
            ($this->resumeCallback)();
        }
    }

    public function pause()
    {
        if ($this->pauseCallback !== null) {
            ($this->pauseCallback)();
        }
    }
}
