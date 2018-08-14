<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\TextDocument;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\TextDocumentSyncKind;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\TextDocumentSyncOptions;
use Tsufeki\Tenkawa\Server\Feature\Common\TextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Feature\Common\TextDocumentItem;
use Tsufeki\Tenkawa\Server\Feature\Common\VersionedTextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class TextDocumentFeature implements Feature, MethodProvider
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(DocumentStore $documentStore, LoggerInterface $logger)
    {
        $this->documentStore = $documentStore;
        $this->logger = $logger;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        $serverCapabilities->textDocumentSync = new TextDocumentSyncOptions();
        $serverCapabilities->textDocumentSync->openClose = true;
        $serverCapabilities->textDocumentSync->change = TextDocumentSyncKind::FULL;

        return;
        yield;
    }

    public function getRequests(): array
    {
        return [];
    }

    public function getNotifications(): array
    {
        return [
            'textDocument/didOpen' => 'didOpenTextDocument',
            'textDocument/didChange' => 'didChangeTextDocument',
            'textDocument/didSave' => 'didSaveTextDocument',
            'textDocument/didClose' => 'didCloseTextDocument',
        ];
    }

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
    public function didOpenTextDocument(TextDocumentItem $textDocument): \Generator
    {
        $time = new Stopwatch();

        yield $this->documentStore->open(
            $textDocument->uri,
            $textDocument->languageId,
            $textDocument->text,
            $textDocument->version
        );

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri [$time]");
    }

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
    public function didChangeTextDocument(VersionedTextDocumentIdentifier $textDocument, array $contentChanges): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        yield $this->documentStore->update(
            $document,
            $contentChanges[0]->text,
            $textDocument->version
        );

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri [$time]");
    }

    /**
     * The document save notification is sent from the client to the server
     * when the document was saved in the client.
     *
     * @param TextDocumentIdentifier $textDocument The document that was saved.
     * @param string|null            $text         Optional the content when saved. Depends on the includeText value
     *                                             when the save notification was requested.
     */
    public function didSaveTextDocument(TextDocumentIdentifier $textDocument, ?string $text = null): \Generator
    {
        $time = new Stopwatch();
        $this->logger->debug(__FUNCTION__ . " $textDocument->uri [$time]");

        return;
        yield;
    }

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
    public function didCloseTextDocument(TextDocumentIdentifier $textDocument): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        yield $this->documentStore->close($document);

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri [$time]");
    }
}
