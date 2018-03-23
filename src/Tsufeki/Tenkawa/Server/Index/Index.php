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
     * @resolve IndexEntry[]
     */
    public function search(Document $document, Query $query): \Generator
    {
        /** @var Project $project */
        $project = yield $this->documentStore->getProjectForDocument($document);

        /** @var IndexStorage $indexStorage */
        $indexStorage = $project->get('index');

        return yield $indexStorage->search($query);
    }
}
