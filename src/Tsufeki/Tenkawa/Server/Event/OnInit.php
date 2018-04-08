<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Event;

interface OnInit
{
    public function onInit(): \Generator;
}
