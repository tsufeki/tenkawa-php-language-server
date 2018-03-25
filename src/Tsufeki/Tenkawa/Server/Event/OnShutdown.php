<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Event;

interface OnShutdown
{
    public function onShutdown(): \Generator;
}
