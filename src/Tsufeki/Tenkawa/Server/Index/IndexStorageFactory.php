<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Index\Storage\WritableIndexStorage;

interface IndexStorageFactory
{
    public function createGlobalIndex(string $indexDataVersion, string $uriPrefixHint = ''): WritableIndexStorage;

    public function createOpenedFilesIndex(Project $project, string $indexDataVersion): WritableIndexStorage;

    public function createProjectFilesIndex(Project $project, string $indexDataVersion): WritableIndexStorage;
}
