<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Workspace;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\WorkspaceFoldersServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\WorkspaceServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class WorkspaceFeature implements Feature, MethodProvider
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $workspaceFoldersSupport = false;

    public function __construct(DocumentStore $documentStore, LoggerInterface $logger)
    {
        $this->documentStore = $documentStore;
        $this->logger = $logger;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        if ($clientCapabilities->workspace && $clientCapabilities->workspace->workspaceFolders) {
            $serverCapabilities->workspace = new WorkspaceServerCapabilities();
            $serverCapabilities->workspace->workspaceFolders = new WorkspaceFoldersServerCapabilities();
            $serverCapabilities->workspace->workspaceFolders->supported = true;
            $serverCapabilities->workspace->workspaceFolders->changeNotifications = true;

            $this->workspaceFoldersSupport = true;
        }

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
            'workspace/didChangeWorkspaceFolders' => 'didChangeWorkspaceFolders',
        ];
    }

    /**
     * @param WorkspaceFolder[]|null $workspaceFolders
     */
    public function openInitialProjects(?string $rootPath, ?Uri $rootUri, ?array $workspaceFolders): \Generator
    {
        /** @var Uri[] $rootUris */
        $rootUris = [];

        if ($this->workspaceFoldersSupport) {
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

        yield $this->documentStore->openDefaultProject();
        yield array_map(function (Uri $uri) {
            $this->logger->debug("Opening project $uri");

            return $this->documentStore->openProject($uri);
        }, $rootUris);
    }

    /**
     * The workspace/didChangeWorkspaceFolders notification is sent from the
     * client to the server to inform the server about workspace folder
     * configuration changes.
     *
     * The notification is sent by default if both
     * ServerCapabilities/workspace/workspaceFolders and
     * ClientCapabilities/workspace/workspaceFolders are true; or if the server
     * has registered to receive this notification first.
     */
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

        yield $coroutines;

        $this->logger->debug(__FUNCTION__ . " [$time]");
    }
}
