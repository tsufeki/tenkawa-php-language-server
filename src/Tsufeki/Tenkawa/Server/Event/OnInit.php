<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Event;

use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\ClientCapabilities;

interface OnInit
{
    public function onInit(ClientCapabilities $clientCapabilities): \Generator;
}
