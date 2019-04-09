<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Index\Storage\MemoryStorage;
use Tsufeki\Tenkawa\Server\Index\Storage\OpenDocumentsStorage;
use Tsufeki\Tenkawa\Server\Index\Storage\WritableIndexStorage;
use Tsufeki\Tenkawa\Server\Uri;

class MemoryIndexStorageFactory implements IndexStorageFactory
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    public function __construct(DocumentStore $documentStore)
    {
        $this->documentStore = $documentStore;
    }

    public function createOpenedFilesIndex(Project $project, string $indexDataVersion): WritableIndexStorage
    {
        return new OpenDocumentsStorage($project, $this->documentStore);
    }

    public function createProjectFilesIndex(Project $project, string $indexDataVersion): WritableIndexStorage
    {
        return new MemoryStorage();
    }

    public function createStubsIndex(Uri $uri, string $indexDataVersion): WritableIndexStorage
    {
        return new MemoryStorage();
    }
}
