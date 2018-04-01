<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server;

use Psr\Log\LoggerInterface;
use Recoil\Recoil;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Event\EventDispatcher;
use Tsufeki\Tenkawa\Server\Event\OnFileChange;
use Tsufeki\Tenkawa\Server\Event\OnInit;
use Tsufeki\Tenkawa\Server\Event\OnShutdown;
use Tsufeki\Tenkawa\Server\Language\CodeActionAggregator;
use Tsufeki\Tenkawa\Server\Language\CommandDispatcher;
use Tsufeki\Tenkawa\Server\Language\CompletionAggregator;
use Tsufeki\Tenkawa\Server\Language\DocumentSymbolsAggregator;
use Tsufeki\Tenkawa\Server\Language\GoToDefinitionAggregator;
use Tsufeki\Tenkawa\Server\Language\HoverAggregator;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Common\Range;
use Tsufeki\Tenkawa\Server\Protocol\Common\TextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Protocol\Common\TextDocumentItem;
use Tsufeki\Tenkawa\Server\Protocol\Common\VersionedTextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Protocol\LanguageServer;
use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\CompletionOptions;
use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\ExecuteCommandOptions;
use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\InitializeResult;
use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\TextDocumentSyncKind;
use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\TextDocumentSyncOptions;
use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\WorkspaceFoldersServerCapabilities;
use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\WorkspaceServerCapabilities;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CodeActionContext;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionContext;
use Tsufeki\Tenkawa\Server\Protocol\Server\Workspace\FileEvent;
use Tsufeki\Tenkawa\Server\Protocol\Server\Workspace\WorkspaceFolder;
use Tsufeki\Tenkawa\Server\Protocol\Server\Workspace\WorkspaceFoldersChangeEvent;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class Server extends LanguageServer
{
    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var CommandDispatcher
     */
    private $commandDispatcher;

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
     * @var CodeActionAggregator
     */
    private $codeActionAggregator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var float
     */
    private $timeout;

    public function __construct(
        EventDispatcher $eventDispatcher,
        DocumentStore $documentStore,
        CommandDispatcher $commandDispatcher,
        CompletionAggregator $completionAggregator,
        HoverAggregator $hoverAggregator,
        GoToDefinitionAggregator $goToDefinitionAggregator,
        DocumentSymbolsAggregator $documentSymbolsAggregator,
        CodeActionAggregator $codeActionAggregator,
        LoggerInterface $logger
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->documentStore = $documentStore;
        $this->commandDispatcher = $commandDispatcher;
        $this->completionAggregator = $completionAggregator;
        $this->hoverAggregator = $hoverAggregator;
        $this->goToDefinitionAggregator = $goToDefinitionAggregator;
        $this->documentSymbolsAggregator = $documentSymbolsAggregator;
        $this->codeActionAggregator = $codeActionAggregator;
        $this->logger = $logger;
        $this->timeout = 30.0;
    }

    /**
     * @param WorkspaceFolder[]|null $workspaceFolders
     *
     * @resolve InitializeResult
     */
    public function initialize(
        int $processId = null,
        string $rootPath = null,
        Uri $rootUri = null,
        $initializationOptions = null,
        ClientCapabilities $capabilities = null,
        string $trace = 'off',
        $workspaceFolders = null
    ): \Generator {
        $time = new Stopwatch();

        /** @var Uri[] $rootUris */
        $rootUris = [];

        $serverCapabilities = new ServerCapabilities();

        if ($capabilities && $capabilities->workspace && $capabilities->workspace->workspaceFolders) {
            $serverCapabilities->workspace = new WorkspaceServerCapabilities();
            $serverCapabilities->workspace->workspaceFolders = new WorkspaceFoldersServerCapabilities();
            $serverCapabilities->workspace->workspaceFolders->supported = true;
            $serverCapabilities->workspace->workspaceFolders->changeNotifications = true;

            /** @var WorkspaceFolder $workspaceFolder */
            foreach ((array)$workspaceFolders as $workspaceFolder) {
                $rootUris[] = $workspaceFolder->uri;
            }
        } else {
            $rootUri = $rootUri ?? ($rootPath ? Uri::fromFilesystemPath($rootPath) : null);
            if ($rootUri !== null) {
                $rootUris[] = $rootUri;
            }
        }

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
        $serverCapabilities->codeActionProvider = $this->codeActionAggregator->hasProviders();
        if ($this->commandDispatcher->hasProviders()) {
            $serverCapabilities->executeCommandProvider = new ExecuteCommandOptions();
            $serverCapabilities->executeCommandProvider->commands = $this->commandDispatcher->getCommands();
        }

        $result = new InitializeResult();
        $result->capabilities = $serverCapabilities;

        yield $this->documentStore->openDefaultProject();
        yield Recoil::timeout($this->timeout, array_map(function (Uri $uri) {
            $this->logger->debug("Opening project $uri");

            return $this->documentStore->openProject($uri);
        }, $rootUris));

        yield Recoil::execute($this->eventDispatcher->dispatch(OnInit::class, $capabilities));

        $this->logger->debug(__FUNCTION__ . " [$time]");

        return $result;
    }

    public function shutdown(): \Generator
    {
        $time = new Stopwatch();

        yield $this->eventDispatcher->dispatchAndWait(OnShutdown::class);
        yield Recoil::timeout($this->timeout, $this->documentStore->closeAll());

        $this->logger->debug(__FUNCTION__ . " [$time]");
    }

    public function exit(): \Generator
    {
        $this->logger->debug(__FUNCTION__);

        exit(0);
        yield;
    }

    public function didChangeWorkspaceFolders(WorkspaceFoldersChangeEvent $event): \Generator
    {
        $time = new Stopwatch();

        $coroutines = [];
        foreach ($event->added as $workspaceFolder) {
            $this->logger->debug("Opening project $workspaceFolder->uri");
            $coroutines[] = $this->documentStore->openProject($workspaceFolder->uri);
        }
        foreach ($event->removed as $workspaceFolder) {
            $this->logger->debug("Closing project $workspaceFolder->uri");
            $project = $this->documentStore->getProject($workspaceFolder->uri);
            $coroutines[] = $this->documentStore->closeProject($project);
        }

        yield Recoil::timeout($this->timeout, $coroutines);

        $this->logger->debug(__FUNCTION__ . " [$time]");
    }

    /**
     * @param FileEvent[] $changes
     */
    public function didChangeWatchedFiles($changes): \Generator
    {
        $uris = array_map(function (FileEvent $event) {
            return $event->uri;
        }, $changes);

        yield $this->eventDispatcher->dispatch(OnFileChange::class, $uris);
        $this->logger->debug(__FUNCTION__);
    }

    public function executeCommand(string $command, array $arguments): \Generator
    {
        $time = new Stopwatch();

        yield $this->commandDispatcher->execute($command, $arguments);

        $this->logger->debug(__FUNCTION__ . " $command [$time]");
    }

    public function didOpenTextDocument(TextDocumentItem $textDocument): \Generator
    {
        $time = new Stopwatch();

        yield Recoil::timeout($this->timeout, $this->documentStore->open(
            $textDocument->uri,
            $textDocument->languageId,
            $textDocument->text,
            $textDocument->version
        ));

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri [$time]");
    }

    public function didChangeTextDocument(VersionedTextDocumentIdentifier $textDocument, array $contentChanges): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        yield Recoil::timeout($this->timeout, $this->documentStore->update(
            $document,
            $contentChanges[0]->text,
            $textDocument->version
        ));

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
        yield Recoil::timeout($this->timeout, $this->documentStore->close($document));

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri [$time]");
    }

    public function completion(
        TextDocumentIdentifier $textDocument,
        Position $position,
        CompletionContext $context = null
    ): \Generator {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        $completions = yield Recoil::timeout($this->timeout, $this->completionAggregator->getCompletions($document, $position, $context));
        $count = count($completions->items);

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri$position [$time, $count items]");

        return $completions;
    }

    public function hover(TextDocumentIdentifier $textDocument, Position $position): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        $hover = yield Recoil::timeout($this->timeout, $this->hoverAggregator->getHover($document, $position));
        $found = $hover ? 'found' : 'not found';

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri$position [$time, $found]");

        return $hover;
    }

    public function definition(TextDocumentIdentifier $textDocument, Position $position): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        $locations = yield Recoil::timeout($this->timeout, $this->goToDefinitionAggregator->getLocations($document, $position));
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
        $symbols = yield Recoil::timeout($this->timeout, $this->documentSymbolsAggregator->getSymbols($document));
        $count = count($symbols);

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri [$time, $count items]");

        return $symbols;
    }

    public function codeAction(TextDocumentIdentifier $textDocument, Range $range, CodeActionContext $context): \Generator
    {
        $time = new Stopwatch();

        $document = $this->documentStore->get($textDocument->uri);
        $commands = yield $this->codeActionAggregator->getCodeActions($document, $range, $context);
        $count = count($commands);

        $this->logger->debug(__FUNCTION__ . " $textDocument->uri$range->start$range->end [$time, $count items]");

        return $commands;
    }
}
