<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa;

use Tsufeki\Tenkawa\Document\DocumentStore;
use Tsufeki\Tenkawa\Protocol\Common\TextDocumentIdentifier;
use Tsufeki\Tenkawa\Protocol\Common\TextDocumentItem;
use Tsufeki\Tenkawa\Protocol\Common\VersionedTextDocumentIdentifier;
use Tsufeki\Tenkawa\Protocol\LanguageServer;
use Tsufeki\Tenkawa\Protocol\Server\LifeCycle\ClientCapabilities;
use Tsufeki\Tenkawa\Protocol\Server\LifeCycle\InitializeResult;
use Tsufeki\Tenkawa\Protocol\Server\LifeCycle\ServerCapabilities;
use Tsufeki\Tenkawa\Protocol\Server\LifeCycle\TextDocumentSyncKind;
use Tsufeki\Tenkawa\Protocol\Server\LifeCycle\TextDocumentSyncOptions;

class Server extends LanguageServer
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @param DocumentStore $documentStore
     */
    public function __construct(DocumentStore $documentStore)
    {
        $this->documentStore = $documentStore;
    }

    /**
     * @resolve InitializeResult
     */
    public function initialize(
        int $processId = null,
        string $rootPath = null,
        Uri $rootUri = null,
        $initializationOptions = null,
        ClientCapabilities $capabilities = null,
        string $trace = 'off'
    ): \Generator {
        $rootUri = $rootUri ?? ($rootPath ? Uri::fromFilesystemPath($rootPath) : null);

        $serverCapabilities = new ServerCapabilities();
        $serverCapabilities->textDocumentSync = new TextDocumentSyncOptions();
        $serverCapabilities->textDocumentSync->openClose = true;
        $serverCapabilities->textDocumentSync->change = TextDocumentSyncKind::FULL;

        $result = new InitializeResult();
        $result->capabilities = $serverCapabilities;

        return $result;
        yield;
    }

    public function shutdown(): \Generator
    {
        $this->documentStore->closeAll();

        return;
        yield;
    }

    public function exit(): \Generator
    {
        exit(0);
        yield;
    }

    public function didOpenTextDocument(TextDocumentItem $textDocument): \Generator
    {
        yield $this->documentStore->open(
            $textDocument->uri,
            $textDocument->languageId,
            $textDocument->text,
            $textDocument->version
        );
    }

    public function didChangeTextDocument(VersionedTextDocumentIdentifier $textDocument, array $contentChanges): \Generator
    {
        $document = $this->documentStore->get($textDocument->uri);
        yield $this->documentStore->update($document, $contentChanges[0]->text, $textDocument->version);
    }

    public function didCloseTextDocument(TextDocumentIdentifier $textDocument): \Generator
    {
        $document = $this->documentStore->get($textDocument->uri);
        yield $this->documentStore->close($document);
    }
}
