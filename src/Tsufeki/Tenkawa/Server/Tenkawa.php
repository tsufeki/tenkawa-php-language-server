<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server;

use Psr\Log\LoggerInterface;
use Recoil\Kernel;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\BlancheJsonRpc\Transport\Transport;
use Tsufeki\HmContainer\Container;
use Tsufeki\Tenkawa\Server\Event\EventDispatcher;
use Tsufeki\Tenkawa\Server\Event\OnStart;
use Tsufeki\Tenkawa\Server\Transport\RunnableTransport;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;
use Tsufeki\Tenkawa\Server\Utils\SyncAsyncKernel;

class Tenkawa
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SyncAsyncKernel
     */
    private $kernel;

    /**
     * @var Plugin[]
     */
    private $plugins;

    /**
     * @param Plugin[] $plugins
     */
    public function __construct(LoggerInterface $logger, SyncAsyncKernel $kernel, array $plugins = [])
    {
        $this->logger = $logger;
        $this->kernel = $kernel;
        $this->plugins = $plugins;
    }

    public function run(RunnableTransport $transport, array $options = []): \Generator
    {
        $time = new Stopwatch();
        $this->logger->debug('start');

        $container = new Container();
        $container->setValue(LoggerInterface::class, $this->logger);
        $container->setValue(Kernel::class, $this->kernel);
        $container->setValue(SyncAsyncKernel::class, $this->kernel);
        $container->setValue(Transport::class, $transport);

        foreach ($this->plugins as $plugin) {
            $plugin->configureContainer($container, $options);
        }

        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $container->get(EventDispatcher::class);
        yield $eventDispatcher->dispatchAndWait(OnStart::class, $options);

        // Materialize the RPC server
        $rpc = $container->get(MappedJsonRpc::class);

        $this->logger->debug("started [$time]");

        yield $transport->run();
    }
}
