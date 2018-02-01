<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Common;

/**
 * A workspace edit represents changes to many resources managed in the workspace.
 *
 * The edit should either provide changes or documentChanges. If the client can
 * handle versioned document edits and if documentChanges are present, the
 * latter are preferred over changes.
 */
class WorkspaceEdit
{
    /**
     * Holds changes to existing resources.
     *
     * @var array<string,TextEdit[]>|null URI => TextEdit[].
     */
    public $changes;

    /**
     * An array of `TextDocumentEdit`s to express changes to n different text documents
     *
     * ...where each text document edit addresses a specific version of a text
     * document.  Whether a client supports versioned document edits is
     * expressed via `WorkspaceClientCapabilities.workspaceEdit.documentChanges`.
     *
     * @var TextDocumentEdit[]|null
     */
    public $documentChanges;
}
