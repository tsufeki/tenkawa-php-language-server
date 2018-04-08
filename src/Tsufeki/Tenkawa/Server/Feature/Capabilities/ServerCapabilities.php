<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Capabilities;

class ServerCapabilities
{
    /**
     * Defines how text documents are synced.
     *
     * @var TextDocumentSyncOptions
     */
    public $textDocumentSync;

    /**
     * The server provides hover support.
     *
     * @var bool
     */
    public $hoverProvider = false;

    /**
     * The server provides completion support.
     *
     * @var CompletionOptions|null
     */
    public $completionProvider;

    /**
     * The server provides goto definition support.
     *
     * @var bool
     */
    public $definitionProvider = false;

    /**
     * The server provides document symbol support.
     *
     * @var bool
     */
    public $documentSymbolProvider = false;

    /**
     * The server provides code actions.
     *
     * @var bool
     */
    public $codeActionProvider = false;

    /**
     * The server provides execute command support.
     *
     * @var ExecuteCommandOptions|null
     */
    public $executeCommandProvider;

    /**
     * Workspace specific server capabilities.
     *
     * @var WorkspaceServerCapabilities|null
     */
    public $workspace;
}
