<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io\FileWatcher;

use Psr\Log\LoggerInterface;
use ReactFilesystemMonitor\FilesystemMonitorFactoryInterface;
use ReactFilesystemMonitor\FilesystemMonitorInterface;
use Recoil\Recoil;
use Tsufeki\Tenkawa\Server\Event\EventDispatcher;
use Tsufeki\Tenkawa\Server\Event\OnFileChange;
use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\Event;
use Tsufeki\Tenkawa\Server\Utils\Platform;

class InotifyWaitFileWatcher implements FileWatcher
{
    /**
     * @var FilesystemMonitorFactoryInterface;
     */
    private $monitorFactory;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var FilesystemMonitorInterface[]
     */
    private $monitors = [];

    const EVENTS = [
        'create',
        'delete',
        'modify',
        'move_from',
        'move_to',
    ];

    public function __construct(
        FilesystemMonitorFactoryInterface $monitorFactory,
        EventDispatcher $eventDispatcher,
        LoggerInterface $logger
    ) {
        $this->monitorFactory = $monitorFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    public function isAvailable(ClientCapabilities $clientCapabilities): bool
    {
        return Platform::isLinux()
            && `which inotifywait 2>/dev/null`;
    }

    public function start(): \Generator
    {
        return;
        yield;
    }

    public function stop(): \Generator
    {
        foreach ($this->monitors as $monitor) {
            $this->stopMonitor($monitor);
        }
        $this->monitors = [];

        return;
        yield;
    }

    public function addDirectory(Uri $uri): \Generator
    {
        if ($uri->getScheme() !== 'file') {
            return;
        }

        $path = $uri->getFilesystemPath();
        if (!file_exists($path)) {
            return;
        }

        $monitor = $this->monitorFactory->create($path, self::EVENTS);

        $monitor->on('all', yield Recoil::callback(function (string $path) {
            $uri = Uri::fromFilesystemPath($path);
            yield $this->eventDispatcher->dispatch(OnFileChange::class, [$uri]);
        }));

        $monitor->on('error', yield Recoil::callback(function (\Throwable $e) {
            $this->logger->error('inotifywait error', ['exception' => $e]);

            return;
            yield;
        }));

        $this->monitors[$uri->getNormalized()] = $monitor;
        $loop = yield Recoil::eventLoop();
        $monitor->start($loop);

        // Wait until watches are set up
        try {
            yield Event::first($monitor, ['start'], ['error']);
            $this->logger->debug('inotifywait set up');
        } catch (\Throwable $e) {
            // Logged in 'error' event handler above.
        }
    }

    public function removeDirectory(Uri $uri): \Generator
    {
        $monitor = $this->monitors[$uri->getNormalized()] ?? null;
        if ($monitor) {
            $this->stopMonitor($monitor);
            unset($this->monitors[$uri->getNormalized()]);
        }

        return;
        yield;
    }

    private function stopMonitor(FilesystemMonitorInterface $monitor)
    {
        $monitor->stop();
        $monitor->removeAllListeners('all');
        $monitor->removeAllListeners('error');
    }
}
