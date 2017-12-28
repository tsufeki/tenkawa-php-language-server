<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index;

use Tsufeki\Tenkawa\Document\Project;
use Tsufeki\Tenkawa\Index\Storage\MemoryStorage;
use Tsufeki\Tenkawa\Index\Storage\SqliteStorage;
use Tsufeki\Tenkawa\Index\Storage\WritableIndexStorage;
use Tsufeki\Tenkawa\Io\Directories;

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

    public function createGlobalIndex(): WritableIndexStorage
    {
        return new SqliteStorage($this->cacheDir . '/global.sqlite');
    }

    public function createOpenedFilesIndex(Project $project): WritableIndexStorage
    {
        return new MemoryStorage();
    }

    public function createProjectFilesIndex(Project $project): WritableIndexStorage
    {
        $hash = sha1((string)$project->getRootUri());

        return new SqliteStorage($this->cacheDir . "/project-$hash.sqlite");
    }
}
