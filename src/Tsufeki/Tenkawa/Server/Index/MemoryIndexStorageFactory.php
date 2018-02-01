<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Index\Storage\MemoryStorage;
use Tsufeki\Tenkawa\Server\Index\Storage\WritableIndexStorage;

class MemoryIndexStorageFactory implements IndexStorageFactory
{
    public function createGlobalIndex(string $indexDataVersion): WritableIndexStorage
    {
        return new MemoryStorage();
    }

    public function createOpenedFilesIndex(Project $project, string $indexDataVersion): WritableIndexStorage
    {
        return new MemoryStorage();
    }

    public function createProjectFilesIndex(Project $project, string $indexDataVersion): WritableIndexStorage
    {
        return new MemoryStorage();
    }
}
