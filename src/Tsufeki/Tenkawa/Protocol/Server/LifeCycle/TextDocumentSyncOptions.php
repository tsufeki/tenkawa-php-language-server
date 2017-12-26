<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Server\LifeCycle;

class TextDocumentSyncOptions
{
    /**
     * Open and close notifications are sent to the server.
     *
     * @var bool
     */
    public $openClose = false;

    /**
     * Change notifications are sent to the server.
     *
     * @see TextDocumentSyncKind
     *
     * @var int
     */
    public $change = TextDocumentSyncKind::NONE;

    /**
     * Will save notifications are sent to the server.
     *
     * @var bool
     */
    public $willSave = false;

    /**
     * Will save wait until requests are sent to the server.
     *
     * @var bool
     */
    public $willSaveWaitUntil = false;

    /**
     * Save notifications are sent to the server.
     *
     * @var SaveOptions|null
     */
    public $save;
}
