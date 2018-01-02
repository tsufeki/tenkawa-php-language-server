<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol;

use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\Tenkawa\Protocol\Common\Location;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Common\TextDocumentIdentifier;
use Tsufeki\Tenkawa\Protocol\Common\TextDocumentItem;
use Tsufeki\Tenkawa\Protocol\Common\VersionedTextDocumentIdentifier;
use Tsufeki\Tenkawa\Protocol\Server\LifeCycle\ClientCapabilities;
use Tsufeki\Tenkawa\Protocol\Server\LifeCycle\InitializeResult;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\TextDocumentContentChangeEvent;
use Tsufeki\Tenkawa\Uri;

abstract class LanguageServer implements MethodProvider
{
    public function getRequests(): array
    {
        return [
            'initialize' => 'initialize',
            'shutdown' => 'shutdown',
            'textDocument/definition' => 'definition',
        ];
    }

    public function getNotifications(): array
    {
        return [
            'exit' => 'exit',
            'textDocument/didOpen' => 'didOpenTextDocument',
            'textDocument/didChange' => 'didChangeTextDocument',
            'textDocument/didClose' => 'didCloseTextDocument',
        ];
    }

    /**
     * @resolve InitializeResult
     */
    abstract public function initialize(
        int $processId = null,
        string $rootPath = null,
        Uri $rootUri = null,
        $initializationOptions = null,
        ClientCapabilities $capabilities = null,
        string $trace = 'off'
    ): \Generator;

    /**
     * The shutdown request is sent from the client to the server. It asks the
     * server to shut down, but to not exit (otherwise the response might not
     * be delivered correctly to the client). There is a separate exit
     * notification that asks the server to exit.
     */
    abstract public function shutdown(): \Generator;

    /**
     * A notification to ask the server to exit its process.
     *
     * The server should exit with success code 0 if the shutdown request has
     * been received before; otherwise with error code 1.
     */
    abstract public function exit(): \Generator;

    /**
     * The document open notification is sent from the client to the server to signal newly opened text documents.
     *
     * The document’s truth is now managed by the client and the server must
     * not try to read the document’s truth using the document’s uri. Open in
     * this sense means it is managed by the client. It doesn’t necessarily
     * mean that its content is presented in an editor. An open notification
     * must not be sent more than once without a corresponding close
     * notification send before. This means open and close notification must be
     * balanced and the max open count is one.
     *
     * @param TextDocumentItem $textDocument The document that was opened.
     */
    abstract public function didOpenTextDocument(TextDocumentItem $textDocument): \Generator;

    /**
     * The document change notification is sent from the client to the server to signal changes to a text document.
     *
     * @param VersionedTextDocumentIdentifier  $textDocument   The document that did change. The version number
     *                                                         points to the version after all provided content changes
     *                                                         have been applied.
     * @param TextDocumentContentChangeEvent[] $contentChanges The actual content changes. The content changes describe
     *                                                         single state changes to the document. So if there are
     *                                                         two content changes c1 and c2 for a document in state S10
     *                                                         then c1 move the document to S11 and c2 to S12.
     */
    abstract public function didChangeTextDocument(VersionedTextDocumentIdentifier $textDocument, array $contentChanges): \Generator;

    /**
     * The document close notification is sent from the client to the server when the document got closed in the client.
     *
     * The document’s truth now exists where the document’s uri points to (e.g.
     * if the document’s uri is a file uri the truth now exists on disk). As
     * with the open notification the close notification is about managing the
     * document’s content. Receiving a close notification doesn’t mean that the
     * document was open in an editor before. A close notification requires
     * a previous open notification to be sent.
     *
     * @param TextDocumentIdentifier $textDocument The document that was closed.
     */
    abstract public function didCloseTextDocument(TextDocumentIdentifier $textDocument): \Generator;

    /**
     * The goto definition request is sent from the client to the server to
     * resolve the definition location of a symbol at a given text document
     * position.
     *
     * @param TextDocumentIdentifier $textDocument The text document.
     * @param Position               $position     The position inside the text document.
     *
     * @resolve Location|Location[]|null
     */
    abstract public function definition(TextDocumentIdentifier $textDocument, Position $position): \Generator;
}
