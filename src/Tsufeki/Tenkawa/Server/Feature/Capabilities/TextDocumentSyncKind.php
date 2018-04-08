<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Capabilities;

/**
 * Defines how the host (editor) should sync document changes to the language server.
 */
class TextDocumentSyncKind
{
    /**
     * Documents should not be synced at all.
     */
    const NONE = 0;

    /**
     * Documents are synced by always sending the full content of the document.
     */
    const FULL = 1;

    /**
     * Documents are synced by sending the full content on open.  After that
     * only incremental updates to the document are send.
     */
    const INCREMENTAL = 2;
}
