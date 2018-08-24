<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\WorkspaceSymbols;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Common\SymbolInformation;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Utils\PriorityKernel\Priority;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class WorkspaceSymbolsFeature implements Feature, MethodProvider
{
    /**
     * @var WorkspaceSymbolsProvider[]
     */
    private $providers;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param WorkspaceSymbolsProvider[] $providers
     */
    public function __construct(array $providers, LoggerInterface $logger)
    {
        $this->providers = $providers;
        $this->logger = $logger;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        $serverCapabilities->workspaceSymbolProvider = !empty($this->providers);

        return;
        yield;
    }

    public function getRequests(): array
    {
        return [
            'workspace/symbol' => 'workspaceSymbol',
        ];
    }

    public function getNotifications(): array
    {
        return [];
    }

    /**
     * The workspace symbol request is sent from the client to the server to
     * list project-wide symbols matching the query string.
     *
     * @param string $query A non-empty query string.
     *
     * @resolve SymbolInformation[]|null
     */
    public function workspaceSymbol(string $query): \Generator
    {
        $time = new Stopwatch();
        yield Priority::interactive(-20);

        $symbols = array_merge(
            ...yield array_map(function (WorkspaceSymbolsProvider $provider) use ($query) {
                return $provider->getSymbols($query);
            }, $this->providers)
        );

        $count = count($symbols);
        $this->logger->debug(__FUNCTION__ . " '$query' [$time, $count items]");

        return $symbols;
    }
}
