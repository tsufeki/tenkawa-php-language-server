<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa;

use Psr\Log\LoggerInterface;
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
use Tsufeki\Tenkawa\Utils\Stopwatch;

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

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        DocumentStore $documentStore,
        CompletionAggregator $completionAggregator,
        HoverAggregator $hoverAggregator,
        GoToDefinitionAggregator $goToDefinitionAggregator,
        DocumentSymbolsAggregator $documentSymbolsAggregator,
        LoggerInterface $logger
    ) {
        $this->documentStore = $documentStore;
        $this->completionAggregator = $completionAggregator;
        $this->hoverAggregator = $hoverAggregator;
        $this->goToDefinitionAggregator = $goToDefinitionAggregator;
        $this->documentSymbolsAggregator = $documentSymbolsAggregator;
        $this->logger = $logger;
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
        $time = new Stopwatch();

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

        $this->logger->debug(__FUNCTION__ . " [$time]");

        return $result;
    }

    public function shutdown(): \Generator
    {
        $time = new Stopwatch();

        yield $this->documentStore->closeAll();

        $this->logger->debug(__FUNCTION__ . " [$time]");
    }

    public function exit(): \Generator
    {
        $this->logger->debug(__FUNCTION__);

        exit(0);
        yield;
    }

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

    public function didChangeTextDocument(VersionedTextDocumentIdentifier $textDocument, array $contentChanges): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        yield $this->documentStore->update($document, $contentChanges[0]->text, $textDocument->version);

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri [$time]");
    }

    public function didSaveTextDocument(TextDocumentIdentifier $textDocument, string $text = null): \Generator
    {
        $time = new Stopwatch();
        $this->logger->debug(__FUNCTION__ . " $textDocument->uri [$time]");

        return;
        yield;
    }

    public function didCloseTextDocument(TextDocumentIdentifier $textDocument): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        yield $this->documentStore->close($document);

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri [$time]");
    }

    public function completion(
        TextDocumentIdentifier $textDocument,
        Position $position,
        CompletionContext $context = null
    ): \Generator {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        $completions = yield $this->completionAggregator->getCompletions($document, $position, $context);
        $count = count($completions->items);

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri$position [$time, $count items]");

        return $completions;
    }

    public function hover(TextDocumentIdentifier $textDocument, Position $position): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        $hover = yield $this->hoverAggregator->getHover($document, $position);
        $found = $hover ? 'found' : 'not found';

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri$position [$time, $found]");

        return $hover;
    }

    public function definition(TextDocumentIdentifier $textDocument, Position $position): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        $locations = yield $this->goToDefinitionAggregator->getLocations($document, $position);
        $count = count($locations);

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri$position [$time, $count items]");

        if ($count === 0) {
            return null;
        }

        if ($count === 1) {
            return $locations[0];
        }

        return $locations;
    }

    public function documentSymbol(TextDocumentIdentifier $textDocument): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        $symbols = yield $this->documentSymbolsAggregator->getSymbols($document);
        $count = count($symbols);

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri [$time, $count items]");

        return $symbols;
    }
}
