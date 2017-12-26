<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Event;

interface OnStart
{
    public function onStart(): \Generator;
}
