<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

use Psr\Log\LoggerInterface;
use Recoil\Recoil;
use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Event\Document\OnProjectClose;
use Tsufeki\Tenkawa\Server\Event\Document\OnProjectOpen;
use Tsufeki\Tenkawa\Server\Event\EventDispatcher;
use Tsufeki\Tenkawa\Server\Event\OnFileChange;
use Tsufeki\Tenkawa\Server\Event\OnInit;
use Tsufeki\Tenkawa\Server\Event\OnShutdown;
use Tsufeki\Tenkawa\Server\Io\FileWatcher\FileWatcher;

class FileWatcherHandler implements OnInit, OnShutdown, OnProjectOpen, OnProjectClose
{
    /**
     * @var FileWatcher[]
     */
    private $fileWatchers;

    /**
     * @var FileWatcher[]
     */
    private $backupFileWatchers;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var FileWatcher[]
     */
    private $activeFileWatchers = [];

    /**
     * @var bool
     */
    private $started = false;

    /**
     * @var Project[]
     */
    private $pendingProjects = [];

    /**
     * @param FileWatcher[] $fileWatchers
     * @param FileWatcher[] $backupFileWatchers
     */
    public function __construct(
        array $fileWatchers,
        array $backupFileWatchers,
        EventDispatcher $eventDispatcher,
        LoggerInterface $logger
    ) {
        $this->fileWatchers = $fileWatchers;
        $this->backupFileWatchers = $backupFileWatchers;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    public function onInit(): \Generator
    {
        $this->activeFileWatchers = [];

        foreach ($this->fileWatchers as $fileWatcher) {
            if ($fileWatcher->isAvailable()) {
                $this->activeFileWatchers[] = $fileWatcher;
                break;
            }
        }

        foreach ($this->backupFileWatchers as $fileWatcher) {
            if ($fileWatcher->isAvailable()) {
                $this->activeFileWatchers[] = $fileWatcher;
            }
        }

        foreach ($this->activeFileWatchers as $fileWatcher) {
            try {
                yield $fileWatcher->start();
            } catch (\Throwable $e) {
                $this->logger->error('File watcher failed to start', ['exception' => $e]);
            }
        }

        $this->started = true;
        foreach ($this->pendingProjects as $project) {
            yield Recoil::execute($this->initProject($project));
        }
        $this->pendingProjects = [];
    }

    public function onProjectOpen(Project $project): \Generator
    {
        if ($this->started) {
            yield Recoil::execute($this->initProject($project));
        } else {
            $this->pendingProjects[] = $project;
        }
    }

    public function onProjectClose(Project $project): \Generator
    {
        foreach ($this->activeFileWatchers as $fileWatcher) {
            try {
                yield $fileWatcher->removeDirectory($project->getRootUri());
            } catch (\Throwable $e) {
                $this->logger->error('File watcher failed to unwatch a directory', ['exception' => $e]);
            }
        }
    }

    public function onShutdown(): \Generator
    {
        foreach ($this->activeFileWatchers as $fileWatcher) {
            yield $fileWatcher->stop();
        }
    }

    private function initProject(Project $project): \Generator
    {
        if (!$project->isClosed()) {
            foreach ($this->activeFileWatchers as $fileWatcher) {
                try {
                    yield $fileWatcher->addDirectory($project->getRootUri());
                } catch (\Throwable $e) {
                    $this->logger->error('File watcher failed to watch a directory', ['exception' => $e]);
                }
            }

            yield $this->eventDispatcher->dispatch(OnFileChange::class, [$project->getRootUri()]);
        }
    }
}
