<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle;

class WorkspaceClientCapabilities
{
    /**
     * The client supports applying batch edits to the workspace by supporting
     * the request 'workspace/applyEdit'
     *
     * @var bool|null
     */
    public $applyEdit;

    /**
     * Capabilities specific to `WorkspaceEdit`s
     *
     * @var WorkspaceEditClientCapabilities|null
     */
    public $workspaceEdit;

    /**
     * Capabilities specific to the `workspace/didChangeWatchedFiles` notification.
     *
     * @var DynamicRegistrationCapability|null
     */
    public $didChangeWatchedFiles;

    /**
     * Capabilities specific to the `workspace/executeCommand` request.
     *
     * @var DynamicRegistrationCapability|null
     */
    public $executeCommand;

    /**
     * The client has support for workspace folders.
     *
     * Since 3.6.0
     *
     * @var bool|null
     */
    public $workspaceFolders;
}
