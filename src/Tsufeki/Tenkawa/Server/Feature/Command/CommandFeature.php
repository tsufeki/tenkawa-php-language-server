<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Command;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\KayoJsonMapper\Mapper;
use Tsufeki\Tenkawa\Server\Exception\UnknownCommandException;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ExecuteCommandOptions;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class CommandFeature implements Feature, MethodProvider
{
    /**
     * @var array<string,CommandProvider>
     */
    private $providers;

    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CommandProvider[] $providers
     */
    public function __construct(array $providers, Mapper $mapper, LoggerInterface $logger)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->getCommand()] = $provider;
        }

        $this->mapper = $mapper;
        $this->logger = $logger;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        if (!empty($this->providers)) {
            $serverCapabilities->executeCommandProvider = new ExecuteCommandOptions();
            $serverCapabilities->executeCommandProvider->commands = array_keys($this->providers);
        }

        return;
        yield;
    }

    public function getRequests(): array
    {
        return [
            'workspace/executeCommand' => 'executeCommand',
        ];
    }

    public function getNotifications(): array
    {
        return [];
    }

    /**
     * The workspace/executeCommand request is sent from the client to the
     * server to trigger command execution on the server.
     *
     * In most cases the server creates a WorkspaceEdit structure and applies
     * the changes to the workspace using the request workspace/applyEdit which
     * is sent from the server to the client.
     *
     * @param string $command   The identifier of the actual command handler.
     * @param array  $arguments Arguments that the command should be invoked with.
     */
    public function executeCommand(string $command, array $arguments): \Generator
    {
        $time = new Stopwatch();

        $provider = $this->providers[$command] ?? null;
        if ($provider === null) {
            throw new UnknownCommandException($command);
        }

        $mappedArgs = $this->mapper->loadArguments($arguments, [$provider, 'execute']);
        yield $provider->execute(...$mappedArgs);

        $this->logger->debug(__FUNCTION__ . " $command [$time]");
    }
}
