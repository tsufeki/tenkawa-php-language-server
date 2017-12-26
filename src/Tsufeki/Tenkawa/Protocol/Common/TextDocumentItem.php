<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Common;

use Tsufeki\Tenkawa\Uri;

/**
 * An item to transfer a text document from the client to the server.
 */
class TextDocumentItem
{
    /**
     * The text document's URI.
     *
     * @var Uri
     */
    public $uri;

    /**
     * The text document's language identifier.
     *
     * @var string
     */
    public $languageId;

    /**
     * The version number of this document.
     *
     * It will increase after each change, including undo/redo.
     *
     * @var int
     */
    public $version;

    /**
     * The content of the opened text document.
     *
     * @var string
     */
    public $text;
}
