<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle;

class ClientCapabilities
{
    /**
     * Workspace specific client capabilities.
     *
     * @var WorkspaceClientCapabilities|null
     */
    public $workspace;
}
