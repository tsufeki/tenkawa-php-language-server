<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Server\Workspace;

use Tsufeki\Tenkawa\Server\Uri;

class WorkspaceFolder
{
    /**
     * The associated URI for this workspace folder.
     *
     * @var Uri
     */
    public $uri;

    /**
     * The name of the workspace folder.
     *
     * Defaults to the uri's basename.
     *
     * @var string
     */
    public $name;
}
