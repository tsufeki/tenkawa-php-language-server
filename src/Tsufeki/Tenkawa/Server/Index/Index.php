<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Index\Storage\IndexStorage;

class Index
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    public function __construct(DocumentStore $documentStore)
    {
        $this->documentStore = $documentStore;
    }

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve IndexEntry[]
     */
    public function search($documentOrProject, Query $query, bool $projectOnly = false): \Generator
    {
        if ($documentOrProject instanceof Document) {
            /** @var Project $project */
            $project = yield $this->documentStore->getProjectForDocument($documentOrProject);
        } else {
            $project = $documentOrProject;
        }

        /** @var IndexStorage $indexStorage */
        $indexStorage = $project->get('index' . ($projectOnly ? '.project_only' : ''));

        return yield $indexStorage->search($query);
    }
}
