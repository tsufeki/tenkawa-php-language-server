<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa;

use Tsufeki\Tenkawa\Document\DocumentStore;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Common\TextDocumentIdentifier;
use Tsufeki\Tenkawa\Protocol\Common\TextDocumentItem;
use Tsufeki\Tenkawa\Protocol\Common\VersionedTextDocumentIdentifier;
use Tsufeki\Tenkawa\Protocol\LanguageServer;
use Tsufeki\Tenkawa\Protocol\Server\LifeCycle\ClientCapabilities;
use Tsufeki\Tenkawa\Protocol\Server\LifeCycle\CompletionOptions;
use Tsufeki\Tenkawa\Protocol\Server\LifeCycle\InitializeResult;
use Tsufeki\Tenkawa\Protocol\Server\LifeCycle\ServerCapabilities;
use Tsufeki\Tenkawa\Protocol\Server\LifeCycle\TextDocumentSyncKind;
use Tsufeki\Tenkawa\Protocol\Server\LifeCycle\TextDocumentSyncOptions;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionContext;
use Tsufeki\Tenkawa\References\CompletionAggregator;
use Tsufeki\Tenkawa\References\DocumentSymbolsAggregator;
use Tsufeki\Tenkawa\References\GoToDefinitionAggregator;
use Tsufeki\Tenkawa\References\HoverAggregator;

class Server extends LanguageServer
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var CompletionAggregator
     */
    private $completionAggregator;

    /**
     * @var HoverAggregator
     */
    private $hoverAggregator;

    /**
     * @var GoToDefinitionAggregator
     */
    private $goToDefinitionAggregator;

    /**
     * @var DocumentSymbolsAggregator
     */
    private $documentSymbolsAggregator;

    public function __construct(
        DocumentStore $documentStore,
        CompletionAggregator $completionAggregator,
        HoverAggregator $hoverAggregator,
        GoToDefinitionAggregator $goToDefinitionAggregator,
        DocumentSymbolsAggregator $documentSymbolsAggregator
    ) {
        $this->documentStore = $documentStore;
        $this->completionAggregator = $completionAggregator;
        $this->hoverAggregator = $hoverAggregator;
        $this->goToDefinitionAggregator = $goToDefinitionAggregator;
        $this->documentSymbolsAggregator = $documentSymbolsAggregator;
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

        if ($rootUri !== null) {
            yield $this->documentStore->openProject($rootUri);
        }

        $serverCapabilities = new ServerCapabilities();
        $serverCapabilities->textDocumentSync = new TextDocumentSyncOptions();
        $serverCapabilities->textDocumentSync->openClose = true;
        $serverCapabilities->textDocumentSync->change = TextDocumentSyncKind::FULL;
        $serverCapabilities->hoverProvider = $this->hoverAggregator->hasProviders();
        if ($this->completionAggregator->hasProviders()) {
            $serverCapabilities->completionProvider = new CompletionOptions();
            $serverCapabilities->completionProvider->triggerCharacters = $this->completionAggregator->getTriggerCharacters();
        }
        $serverCapabilities->definitionProvider = $this->goToDefinitionAggregator->hasProviders();
        $serverCapabilities->documentSymbolProvider = $this->documentSymbolsAggregator->hasProviders();

        $result = new InitializeResult();
        $result->capabilities = $serverCapabilities;

        return $result;
    }

    public function shutdown(): \Generator
    {
        yield $this->documentStore->closeAll();
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

    public function didSaveTextDocument(TextDocumentIdentifier $textDocument, string $text = null): \Generator
    {
        return;
        yield;
    }

    public function didCloseTextDocument(TextDocumentIdentifier $textDocument): \Generator
    {
        $document = $this->documentStore->get($textDocument->uri);
        yield $this->documentStore->close($document);
    }

    public function completion(
        TextDocumentIdentifier $textDocument,
        Position $position,
        CompletionContext $context = null
    ): \Generator {
        $document = $this->documentStore->get($textDocument->uri);

        return yield $this->completionAggregator->getCompletions($document, $position, $context);
    }

    public function hover(TextDocumentIdentifier $textDocument, Position $position): \Generator
    {
        $document = $this->documentStore->get($textDocument->uri);

        return yield $this->hoverAggregator->getHover($document, $position);
    }

    public function definition(TextDocumentIdentifier $textDocument, Position $position): \Generator
    {
        $document = $this->documentStore->get($textDocument->uri);
        $locations = yield $this->goToDefinitionAggregator->getLocations($document, $position);

        if (count($locations) === 0) {
            return null;
        }

        if (count($locations) === 1) {
            return $locations[0];
        }

        return $locations;
    }

    public function documentSymbol(TextDocumentIdentifier $textDocument): \Generator
    {
        $document = $this->documentStore->get($textDocument->uri);

        return yield $this->documentSymbolsAggregator->getSymbols($document);
    }
}
