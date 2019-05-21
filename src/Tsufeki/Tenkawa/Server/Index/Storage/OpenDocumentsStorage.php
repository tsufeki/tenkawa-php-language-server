<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index\Storage;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Exception\DocumentNotOpenException;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class OpenDocumentsStorage implements WritableIndexStorage
{
    /**
     * @var Project
     */
    private $project;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    private const KEY = 'index.entries';

    public function __construct(Project $project, DocumentStore $documentStore)
    {
        $this->project = $project;
        $this->documentStore = $documentStore;
    }

    public function search(Query $query): \Generator
    {
        /** @var Document[] $documents */
        $documents = [];
        if ($query->uri === null) {
            $documents = yield $this->documentStore->getDocumentsForProject($this->project);
        } else {
            try {
                $documents = [$this->documentStore->get($query->uri)];
            } catch (DocumentNotOpenException $e) {
            }
        }

        $result = [];
        foreach ($documents as $document) {
            /** @var array<string,IndexEntry[]> */
            $entriesByCategory = $document->get(self::KEY);
            $categories = $query->category !== null ? [$query->category] : array_keys($entriesByCategory);
            foreach ($categories as $category) {
                foreach ($entriesByCategory[$category] ?? [] as $entry) {
                    if ($query->key !== null) {
                        if ($query->match === Query::FULL && $entry->key !== $query->key) {
                            continue;
                        }

                        if ($query->match === Query::PREFIX && !StringUtils::startsWith($entry->key, $query->key)) {
                            continue;
                        }

                        if ($query->match === Query::SUFFIX && !StringUtils::endsWith($entry->key, $query->key)) {
                            continue;
                        }
                    }

                    if ($query->tag !== null && !in_array($entry->tag, $query->tag, true)) {
                        continue;
                    }

                    $result[] = $entry;
                }
            }
        }

        return $result;
        yield;
    }

    /**
     * @param IndexEntry[] $entries
     */
    public function replaceFile(Uri $uri, array $entries, ?string $stamp): \Generator
    {
        try {
            $document = $this->documentStore->get($uri);
            $entriesByCategory = [];

            foreach ($entries as $entry) {
                $entry->sourceUri = $uri;
                $entriesByCategory[$entry->category][] = $entry;
            }

            $document->set(self::KEY, $entriesByCategory ?: null);
        } catch (DocumentNotOpenException $e) {
            // TODO ignore completely?
            if (!empty($entries)) {
                throw $e;
            }
        }

        return;
        yield;
    }

    public function getFileStamps(?Uri $filterUri = null): \Generator
    {
        $stamps = [];
        /** @var Document $document */
        foreach (yield $this->documentStore->getDocumentsForProject($this->project) as $document) {
            if ($document->get(self::KEY) !== null &&
                ($filterUri === null
                || $filterUri->equals($document->getUri())
                || $filterUri->isParentOf($document->getUri()))
            ) {
                $stamps[$document->getUri()->getNormalized()] = null;
            }
        }

        return $stamps;
        yield;
    }
}
