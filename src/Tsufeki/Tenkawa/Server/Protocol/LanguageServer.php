<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol;

use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\Tenkawa\Server\Protocol\Common\Location;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Common\TextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Protocol\Common\TextDocumentItem;
use Tsufeki\Tenkawa\Server\Protocol\Common\VersionedTextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\InitializeResult;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionContext;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionItem;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionList;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\Hover;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\SymbolInformation;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\TextDocumentContentChangeEvent;
use Tsufeki\Tenkawa\Server\Protocol\Server\Workspace\WorkspaceFolder;
use Tsufeki\Tenkawa\Server\Protocol\Server\Workspace\WorkspaceFoldersChangeEvent;
use Tsufeki\Tenkawa\Server\Uri;

abstract class LanguageServer implements MethodProvider
{
    public function getRequests(): array
    {
        return [
            'initialize' => 'initialize',
            'shutdown' => 'shutdown',
            'textDocument/completion' => 'completion',
            'textDocument/hover' => 'hover',
            'textDocument/definition' => 'definition',
            'textDocument/documentSymbol' => 'documentSymbol',
        ];
    }

    public function getNotifications(): array
    {
        return [
            'exit' => 'exit',
            'workspace/didChangeWorkspaceFolders' => 'didChangeWorkspaceFolders',
            'textDocument/didOpen' => 'didOpenTextDocument',
            'textDocument/didChange' => 'didChangeTextDocument',
            'textDocument/didSave' => 'didSaveTextDocument',
            'textDocument/didClose' => 'didCloseTextDocument',
        ];
    }

    /**
     * @param WorkspaceFolder[]|null $workspaceFolders
     *
     * @resolve InitializeResult
     */
    abstract public function initialize(
        int $processId = null,
        string $rootPath = null,
        Uri $rootUri = null,
        $initializationOptions = null,
        ClientCapabilities $capabilities = null,
        string $trace = 'off',
        array $workspaceFolders = null
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
     * The workspace/didChangeWorkspaceFolders notification is sent from the
     * client to the server to inform the server about workspace folder
     * configuration changes.
     *
     * The notification is sent by default if both
     * ServerCapabilities/workspace/workspaceFolders and
     * ClientCapabilities/workapce/workspaceFolders are true; or if the server
     * has registered to receive this notification first.
     */
    abstract public function didChangeWorkspaceFolders(WorkspaceFoldersChangeEvent $event): \Generator;

    /**
     * The document open notification is sent from the client to the server to
     * signal newly opened text documents.
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
     * The document change notification is sent from the client to the server
     * to signal changes to a text document.
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
     * The document save notification is sent from the client to the server
     * when the document was saved in the client.
     *
     * @param TextDocumentIdentifier $textDocument The document that was saved.
     * @param string|null            $text         Optional the content when saved. Depends on the includeText value
     *                                             when the save notification was requested.
     */
    abstract public function didSaveTextDocument(TextDocumentIdentifier $textDocument, string $text = null): \Generator;

    /**
     * The document close notification is sent from the client to the server
     * when the document got closed in the client.
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
     * The completion request is sent from the client to the server to compute
     * completion items at a given cursor position.
     *
     * Completion items are presented in the IntelliSense user interface. If
     * computing full completion items is expensive, servers can additionally
     * provide a handler for the completion item resolve request
     * (‘completionItem/resolve’).
     *
     * @param TextDocumentIdentifier $textDocument The text document.
     * @param Position               $position     The position inside the text document.
     * @param CompletionContext      $context      The completion context. This is only available it the client
     *                                             specifies to send this using
     *                                             `ClientCapabilities.textDocument.completion.contextSupport === true`
     *
     * @resolve CompletionItem[]|CompletionList|null If a CompletionItem[] is provided it is interpreted to be complete.
     */
    abstract public function completion(
        TextDocumentIdentifier $textDocument,
        Position $position,
        CompletionContext $context = null
    ): \Generator;

    /**
     * The hover request is sent from the client to the server to request hover
     * information at a given text document position.
     *
     * @param TextDocumentIdentifier $textDocument The text document.
     * @param Position               $position     The position inside the text document.
     *
     * @resolve Hover|null
     */
    abstract public function hover(TextDocumentIdentifier $textDocument, Position $position): \Generator;

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

    /**
     * The document symbol request is sent from the client to the server to
     * return a flat list of all symbols found in a given text document.
     *
     * Neither the document’s location range nor the documents container name
     * should be used to infer a hierarchy.
     *
     * @param TextDocumentIdentifier $textDocument The text document.
     *
     * @resolve SymbolInformation[]|null
     */
    abstract public function documentSymbol(TextDocumentIdentifier $textDocument): \Generator;
}
