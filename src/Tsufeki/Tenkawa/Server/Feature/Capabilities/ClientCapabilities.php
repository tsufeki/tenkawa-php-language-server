<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Capabilities;

class ClientCapabilities
{
    /**
     * Workspace specific client capabilities.
     *
     * @var WorkspaceClientCapabilities|null
     */
    public $workspace;

    /**
     * Text document specific client capabilities.
     *
     * @var TextDocumentClientCapabilities|null
     */
    public $textDocument;

    /**
     * Experimental client capabilities.
     *
     * @var mixed|null
     */
    public $experimental;
}
