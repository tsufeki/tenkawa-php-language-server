<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils\PriorityKernel;

use Recoil\Kernel\SystemStrand;

interface Scheduler
{
    public function scheduleStart(SystemStrand $strand, float $delay = 0.0): void;

    public function scheduleSend(SystemStrand $strand, float $delay = 0.0, $value = null): void;

    public function scheduleThrow(SystemStrand $strand, \Throwable $exception): void;
}
