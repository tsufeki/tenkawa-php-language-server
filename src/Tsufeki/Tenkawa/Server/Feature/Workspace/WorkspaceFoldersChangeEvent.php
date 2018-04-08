<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Workspace;

/**
 * The workspace folder change event.
 */
class WorkspaceFoldersChangeEvent
{
    /**
     * The array of added workspace folders
     *
     * @var WorkspaceFolder[]
     */
    public $added;

    /**
     * The array of the removed workspace folders
     *
     * @var WorkspaceFolder[]
     */
    public $removed;
}
