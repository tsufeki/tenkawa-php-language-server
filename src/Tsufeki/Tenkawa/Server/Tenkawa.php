<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Recoil\Exception\StrandException;
use Recoil\Kernel;
use Recoil\React\ReactKernel;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\BlancheJsonRpc\Transport\Transport;
use Tsufeki\HmContainer\Container;
use Tsufeki\Tenkawa\Server\Event\EventDispatcher;
use Tsufeki\Tenkawa\Server\Event\OnStart;
use Tsufeki\Tenkawa\Server\Exception\IoException;
use Tsufeki\Tenkawa\Server\Logger\CompositeLogger;
use Tsufeki\Tenkawa\Server\Logger\LevelFilteringLogger;
use Tsufeki\Tenkawa\Server\Logger\StreamLogger;
use Tsufeki\Tenkawa\Server\Transport\RunnableTransport;
use Tsufeki\Tenkawa\Server\Transport\StreamTransport;
use Tsufeki\Tenkawa\Server\Utils\NestedKernelsSyncAsync;
use Tsufeki\Tenkawa\Server\Utils\PriorityKernel\ScheduledReactKernel;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;
use Tsufeki\Tenkawa\Server\Utils\SyncAsync;

class Tenkawa
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var Plugin[]
     */
    private $plugins;

    /**
     * @param Plugin[] $plugins
     */
    public function __construct(Kernel $kernel, LoggerInterface $logger, array $plugins = [])
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
        $container->setValue(SyncAsync::class, new NestedKernelsSyncAsync([ReactKernel::class, 'create']));
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

    public static function main(array $cmdLineArgs): void
    {
        $options = self::parseArgs($cmdLineArgs);

        $kernel = ScheduledReactKernel::create();
        $logger = new CompositeLogger();
        self::setupErrorHandlers($logger, $kernel);
        self::setupLoggers($logger, $options);
        $logger->debug('PHP ' . PHP_VERSION . ' ' . PHP_OS);

        $plugins = (new PluginFinder())->findPlugins();
        $transport = self::createTransport($options);

        $app = new self($kernel, $logger, $plugins);
        $kernel->execute($app->run($transport, $options));
        $kernel->run();
    }

    private static function parseArgs(array $cmdLineArgs): array
    {
        array_shift($cmdLineArgs);
        $options = [
            'log.stderr' => false,
            'log.file' => false,
            'log.client' => false,
            'log.level' => LogLevel::INFO,
            'transport.socket' => false,
        ];

        foreach ($cmdLineArgs as $arg) {
            if (StringUtils::startsWith($arg, '--')) {
                [$option, $value] = [$arg, null];
                if (strpos($arg, '=') !== false) {
                    [$option, $value] = explode('=', $arg, 2);
                }

                switch ($option) {
                    case '--log-stderr':
                        $options['log.stderr'] = true;
                        continue 2;
                    case '--log-file':
                        $options['log.file'] = $value;
                        continue 2;
                    case '--log-client':
                        $options['log.client'] = true;
                        continue 2;
                    case '--log-level':
                        $options['log.level'] = $value;
                        continue 2;
                    case '--socket':
                        $options['transport.socket'] = $value;
                        continue 2;
                }
            }

            throw new \RuntimeException("Unrecognized argument: $arg");
        }

        return $options;
    }

    private static function setupErrorHandlers(LoggerInterface $logger, Kernel $kernel): void
    {
        set_error_handler(function (int $severity, string $message, string $file, int $line) {
            if (!(error_reporting() & $severity)) {
                return;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (\Throwable $e) use ($logger) {
            $logger->critical($e->getMessage(), ['exception' => $e]);
        });

        $kernel->setExceptionHandler(function (\Throwable $e) use ($logger) {
            if (!($e instanceof StrandException)) {
                throw $e;
            }

            $logger->error($e->getMessage(), ['exception' => $e->getPrevious()]);
        });
    }

    private static function setupLoggers(CompositeLogger $logger, array $options): void
    {
        /** @var resource|false|null */
        $stream = null;

        if ($options['log.stderr']) {
            $stream = STDERR;
        }

        if ($options['log.file']) {
            $stream = fopen($options['log.file'], 'a');
        }

        if ($stream) {
            $logger->add(new LevelFilteringLogger(
                new StreamLogger($stream),
                $options['log.level']
            ));
        }
    }

    private static function createTransport(array $options): RunnableTransport
    {
        if ($options['transport.socket'] ?? false) {
            $socket = stream_socket_client($options['transport.socket']);
            if ($socket === false) {
                throw new IoException("Can't open a connection to client");
            }
            stream_set_blocking($socket, false);
            $transport = new StreamTransport($socket, $socket);
        } else {
            stream_set_blocking(STDIN, false);
            stream_set_blocking(STDOUT, false);
            $transport = new StreamTransport(STDIN, STDOUT);
        }

        return $transport;
    }
}
