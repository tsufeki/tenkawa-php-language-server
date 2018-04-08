<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

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
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var FileWatcher|null
     */
    private $activeFileWatcher;

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
     */
    public function __construct(array $fileWatchers, EventDispatcher $eventDispatcher)
    {
        $this->fileWatchers = $fileWatchers;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function onInit(): \Generator
    {
        $this->activeFileWatcher = null;
        foreach ($this->fileWatchers as $fileWatcher) {
            if ($fileWatcher->isAvailable()) {
                $this->activeFileWatcher = $fileWatcher;
                break;
            }
        }

        if ($this->activeFileWatcher !== null) {
            yield $this->activeFileWatcher->start();
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
        if ($this->activeFileWatcher !== null) {
            yield $this->activeFileWatcher->removeDirectory($project->getRootUri());
        }
    }

    public function onShutdown(): \Generator
    {
        if ($this->activeFileWatcher !== null) {
            yield $this->activeFileWatcher->stop();
        }
    }

    private function initProject(Project $project): \Generator
    {
        if (!$project->isClosed()) {
            if ($this->activeFileWatcher !== null) {
                yield $this->activeFileWatcher->addDirectory($project->getRootUri());
            }

            yield $this->eventDispatcher->dispatch(OnFileChange::class, [$project->getRootUri()]);
        }
    }
}
