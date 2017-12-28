<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index;

use Tsufeki\Tenkawa\Document\Project;
use Tsufeki\Tenkawa\Index\Storage\WritableIndexStorage;

interface IndexStorageFactory
{
    public function createGlobalIndex(): WritableIndexStorage;

    public function createOpenedFilesIndex(Project $project): WritableIndexStorage;

    public function createProjectFilesIndex(Project $project): WritableIndexStorage;
}
