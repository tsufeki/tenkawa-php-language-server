<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle;

class WorkspaceServerCapabilities
{
    /**
     * The server supports workspace folders.
     *
     * Since 3.6.0
     *
     * @var WorkspaceFoldersServerCapabilities|null
     */
    public $workspaceFolders;
}
