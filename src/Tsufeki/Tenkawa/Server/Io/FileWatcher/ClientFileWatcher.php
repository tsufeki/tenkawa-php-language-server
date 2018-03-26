<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Io\FileWatcher;

use Tsufeki\Tenkawa\Server\Protocol\Client\FileSystemWatcher;
use Tsufeki\Tenkawa\Server\Protocol\Client\Unregistration;
use Tsufeki\Tenkawa\Server\Protocol\Client\WatchKind;
use Tsufeki\Tenkawa\Server\Protocol\LanguageClient;
use Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Uri;

class ClientFileWatcher implements FileWatcher
{
    /**
     * @var LanguageClient
     */
    private $client;

    /**
     * @var Unregistration|null
     */
    private $unregistration;

    public function __construct(LanguageClient $client)
    {
        $this->client = $client;
    }

    public function isAvailable(ClientCapabilities $clientCapabilities): bool
    {
        return $clientCapabilities->workspace
            && $clientCapabilities->workspace->didChangeWatchedFiles
            && $clientCapabilities->workspace->didChangeWatchedFiles->dynamicRegistration;
    }

    public function start(): \Generator
    {
        $fileSystemWatcher = new FileSystemWatcher();
        $fileSystemWatcher->globPattern = '**/*';
        $fileSystemWatcher->kind = WatchKind::CREATE | WatchKind::DELETE | WatchKind::CHANGE;

        $this->unregistration = yield $this->client->registerFileSystemWatchers([$fileSystemWatcher]);
    }

    public function stop(): \Generator
    {
        if ($this->unregistration !== null) {
            yield $this->client->unregisterCapability([$this->unregistration]);
        }
    }

    public function addDirectory(Uri $uri): \Generator
    {
        yield;
    }
}
