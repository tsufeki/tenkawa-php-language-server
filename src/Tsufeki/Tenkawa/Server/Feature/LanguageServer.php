<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Event\EventDispatcher;
use Tsufeki\Tenkawa\Server\Event\OnInit;
use Tsufeki\Tenkawa\Server\Event\OnShutdown;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\InitializeResult;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Workspace\WorkspaceFeature;
use Tsufeki\Tenkawa\Server\Feature\Workspace\WorkspaceFolder;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class LanguageServer implements MethodProvider
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Feature[]
     */
    private $features;

    /**
     * @var WorkspaceFeature
     */
    private $workspaceFeature;

    /**
     * @param Feature[] $features
     */
    public function __construct(
        EventDispatcher $eventDispatcher,
        DocumentStore $documentStore,
        LoggerInterface $logger,
        array $features,
        WorkspaceFeature $workspaceFeature
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->documentStore = $documentStore;
        $this->logger = $logger;
        $this->features = $features;
        $this->workspaceFeature = $workspaceFeature;
    }

    public function getRequests(): array
    {
        return [
            'initialize' => 'initialize',
            'shutdown' => 'shutdown',
        ];
    }

    public function getNotifications(): array
    {
        return [
            'exit' => 'exit',
        ];
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

        $capabilities = $capabilities ?? new ClientCapabilities();
        $serverCapabilities = new ServerCapabilities();
        foreach ($this->features as $feature) {
            yield $feature->initialize($capabilities, $serverCapabilities);
        }

        yield $this->workspaceFeature->openInitialProjects($rootPath, $rootUri, $workspaceFolders);

        $result = new InitializeResult();
        $result->capabilities = $serverCapabilities;

        yield $this->eventDispatcher->dispatch(OnInit::class);

        $this->logger->debug(__FUNCTION__ . " [$time]");

        return $result;
    }

    /**
     * The shutdown request is sent from the client to the server. It asks the
     * server to shut down, but to not exit (otherwise the response might not
     * be delivered correctly to the client). There is a separate exit
     * notification that asks the server to exit.
     */
    public function shutdown(): \Generator
    {
        $time = new Stopwatch();

        yield $this->eventDispatcher->dispatchAndWait(OnShutdown::class);
        yield $this->documentStore->closeAll();

        $this->logger->debug(__FUNCTION__ . " [$time]");
    }

    /**
     * A notification to ask the server to exit its process.
     *
     * The server should exit with success code 0 if the shutdown request has
     * been received before; otherwise with error code 1.
     */
    public function exit(): \Generator
    {
        $this->logger->debug(__FUNCTION__);

        exit(0);
        yield;
    }
}
