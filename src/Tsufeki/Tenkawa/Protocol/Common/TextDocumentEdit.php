<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Common;

/**
 * Describes textual changes on a single text document.
 *
 * The text document is referred to as a VersionedTextDocumentIdentifier to
 * allow clients to check the text document version before an edit is applied.
 * A TextDocumentEdit describes all changes on a version Si and after they are
 * applied move the document to version Si+1. So the creator of
 * a TextDocumentEdit doesn’t need to sort the array or do any kind of
 * ordering. However the edits must be non overlapping.
 */
class TextDocumentEdit
{
    /**
     * The text document to change.
     *
     * @var VersionedTextDocumentIdentifier
     */
    public $textDocument;

    /**
     * The edits to be applied.
     *
     * @var TextEdit[]
     */
    public $edits;
}
