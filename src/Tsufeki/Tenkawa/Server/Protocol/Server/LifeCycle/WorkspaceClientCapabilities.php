<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle;

class WorkspaceClientCapabilities
{
    /**
     * Capabilities specific to the `workspace/didChangeWatchedFiles` notification.
     *
     * @var DynamicRegistrationCapability|null
     */
    public $didChangeWatchedFiles;

    /**
     * The client has support for workspace folders.
     *
     * Since 3.6.0
     *
     * @var bool|null
     */
    public $workspaceFolders;
}
