<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Server\LifeCycle;

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
}
