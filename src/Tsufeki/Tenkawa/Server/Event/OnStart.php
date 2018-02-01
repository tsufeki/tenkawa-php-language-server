<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Event;

interface OnStart
{
    public function onStart(array $options): \Generator;
}
