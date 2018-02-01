<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Index\Storage\MemoryStorage;
use Tsufeki\Tenkawa\Server\Index\Storage\SqliteStorage;
use Tsufeki\Tenkawa\Server\Index\Storage\WritableIndexStorage;
use Tsufeki\Tenkawa\Server\Io\Directories;

class LocalCacheIndexStorageFactory implements IndexStorageFactory
{
    /**
     * @var string
     */
    private $cacheDir;

    public function __construct(Directories $dirs)
    {
        $this->cacheDir = $dirs->getCacheDir() . '/index';
    }

    public function createGlobalIndex(string $indexDataVersion): WritableIndexStorage
    {
        return new SqliteStorage($this->cacheDir . '/global.sqlite', $indexDataVersion);
    }

    public function createOpenedFilesIndex(Project $project, string $indexDataVersion): WritableIndexStorage
    {
        return new MemoryStorage();
    }

    public function createProjectFilesIndex(Project $project, string $indexDataVersion): WritableIndexStorage
    {
        $hash = sha1((string)$project->getRootUri());

        return new SqliteStorage($this->cacheDir . "/project-$hash.sqlite", $indexDataVersion);
    }
}
