<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Index\Storage\OpenDocumentsStorage;
use Tsufeki\Tenkawa\Server\Index\Storage\SqliteStorage;
use Tsufeki\Tenkawa\Server\Index\Storage\WritableIndexStorage;
use Tsufeki\Tenkawa\Server\Io\Directories;

class LocalCacheIndexStorageFactory implements IndexStorageFactory
{
    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    public function __construct(Directories $dirs, DocumentStore $documentStore)
    {
        $this->cacheDir = $dirs->getCacheDir() . '/index';
        $this->documentStore = $documentStore;
    }

    public function createOpenedFilesIndex(Project $project, string $indexDataVersion): WritableIndexStorage
    {
        return new OpenDocumentsStorage($project, $this->documentStore);
    }

    public function createProjectFilesIndex(Project $project, string $indexDataVersion): WritableIndexStorage
    {
        $hash = sha1((string)$project->getRootUri());

        return new SqliteStorage(
            $this->cacheDir . "/project-$hash.sqlite",
            $indexDataVersion,
            (string)$project->getRootUri()
        );
    }
}
