<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Common;

/**
 * An identifier to denote a specific version of a text document.
 */
class VersionedTextDocumentIdentifier extends TextDocumentIdentifier
{
    /**
     * The version number of this document.
     *
     * If a versioned text document identifier is sent from the server to the
     * client and the file is not open in the editor (the server has not
     * received an open notification before) the server can send `null` to
     * indicate that the version is known and the content on disk is the truth
     * (as speced with document content ownership)
     *
     * @var int|null
     */
    public $version;
}
