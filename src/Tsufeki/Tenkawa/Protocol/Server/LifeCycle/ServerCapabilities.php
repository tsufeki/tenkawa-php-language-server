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
     * The server provides goto definition support.
     *
     * @var bool
     */
    public $definitionProvider = false;
}
