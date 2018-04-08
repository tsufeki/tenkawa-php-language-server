<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodRegistry;
use Tsufeki\BlancheJsonRpc\Dispatcher\SimpleMethodRegistry;
use Tsufeki\Tenkawa\Server\Event\OnStart;
use Tsufeki\Tenkawa\Server\Logger\ClientLogger;
use Tsufeki\Tenkawa\Server\Logger\CompositeLogger;
use Tsufeki\Tenkawa\Server\Logger\LevelFilteringLogger;

class ServerPluginInit implements OnStart
{
    /**
     * @var MethodRegistry
     */
    private $methodRegistry;

    /**
     * @var MethodProvider[]
     */
    private $methodProviders;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ClientLogger
     */
    private $clientLogger;

    /**
     * @param MethodProvider[] $methodProviders
     */
    public function __construct(
        MethodRegistry $methodRegistry,
        array $methodProviders,
        LoggerInterface $logger,
        ClientLogger $clientLogger
    ) {
        $this->methodRegistry = $methodRegistry;
        $this->methodProviders = $methodProviders;
        $this->logger = $logger;
        $this->clientLogger = $clientLogger;
    }

    public function onStart(array $options): \Generator
    {
        if ($this->methodRegistry instanceof SimpleMethodRegistry) {
            foreach ($this->methodProviders as $provider) {
                $this->methodRegistry->addProvider($provider);
            }
        }

        if ($this->logger instanceof CompositeLogger) {
            if ($options['log.client'] ?? false) {
                $this->logger->add(new LevelFilteringLogger($this->clientLogger, LogLevel::INFO));
            }
        }

        return;
        yield;
    }
}
