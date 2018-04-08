<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature;

use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;

interface Feature
{
    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator;
}
