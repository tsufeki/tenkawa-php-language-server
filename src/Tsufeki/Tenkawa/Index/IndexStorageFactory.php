<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index;

use Tsufeki\Tenkawa\Document\Project;
use Tsufeki\Tenkawa\Index\Storage\WritableIndexStorage;

interface IndexStorageFactory
{
    public function createGlobalIndex(string $indexDataVersion): WritableIndexStorage;

    public function createOpenedFilesIndex(Project $project, string $indexDataVersion): WritableIndexStorage;

    public function createProjectFilesIndex(Project $project, string $indexDataVersion): WritableIndexStorage;
}
