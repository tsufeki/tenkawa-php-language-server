<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\FileWatcher;

use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\Tenkawa\Server\Event\EventDispatcher;
use Tsufeki\Tenkawa\Server\Event\OnFileChange;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Feature\Registration\Registration;
use Tsufeki\Tenkawa\Server\Feature\Registration\RegistrationFeature;
use Tsufeki\Tenkawa\Server\Feature\Registration\Unregistration;
use Tsufeki\Tenkawa\Server\Io\FileWatcher\FileWatcher;
use Tsufeki\Tenkawa\Server\Uri;

class FileWatcherFeature implements Feature, FileWatcher, MethodProvider
{
    /**
     * @var RegistrationFeature
     */
    private $registrationFeature;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $available = false;

    /**
     * @var Unregistration|null
     */
    private $unregistration;

    public function __construct(
        RegistrationFeature $registrationFeature,
        EventDispatcher $eventDispatcher,
        LoggerInterface $logger
    ) {
        $this->registrationFeature = $registrationFeature;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        $this->available = $clientCapabilities->workspace
            && $clientCapabilities->workspace->didChangeWatchedFiles
            && $clientCapabilities->workspace->didChangeWatchedFiles->dynamicRegistration;

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
            'workspace/didChangeWatchedFiles' => 'didChangeWatchedFiles',
        ];
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function start(): \Generator
    {
        $fileSystemWatcher = new FileSystemWatcher();
        $fileSystemWatcher->globPattern = '**';
        $fileSystemWatcher->kind = WatchKind::CREATE | WatchKind::DELETE | WatchKind::CHANGE;

        $this->unregistration = yield $this->registerFileSystemWatchers([$fileSystemWatcher]);
    }

    public function stop(): \Generator
    {
        if ($this->unregistration !== null) {
            yield $this->registrationFeature->unregisterCapability([$this->unregistration]);
        }
    }

    public function addDirectory(Uri $uri): \Generator
    {
        return;
        yield;
    }

    public function removeDirectory(Uri $uri): \Generator
    {
        return;
        yield;
    }

    /**
     * Register for workspace/didChangeWatchedFiles notification.
     *
     * @param FileSystemWatcher[] $watchers
     *
     * @resolve Unregistration
     */
    private function registerFileSystemWatchers(array $watchers): \Generator
    {
        $registration = new Registration();

        $registration->method = 'workspace/didChangeWatchedFiles';
        $options = new DidChangeWatchedFilesRegistrationOptions();
        $options->watchers = $watchers;
        $registration->registerOptions = $options;

        $unregistrations = yield $this->registrationFeature->registerCapability([$registration]);

        return $unregistrations[0];
    }

    /**
     * The watched files notification is sent from the client to the server
     * when the client detects changes to files watched by the language client.
     *
     * It is recommended that servers register for these file events using the
     * registration mechanism. In former implementations clients pushed file
     * events without the server actively asking for it.
     *
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
}
