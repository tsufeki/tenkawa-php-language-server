<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index\Storage;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Exception\DocumentNotOpenException;
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

    const KEY = 'index.entries';

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
            foreach ($document->get(self::KEY) ?? [] as $entry) {
                if ($query->category !== null && $entry->category !== $query->category) {
                    continue;
                }

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

                $result[] = $entry;
            }
        }

        return $result;
        yield;
    }

    public function replaceFile(Uri $uri, array $entries, int $timestamp = null): \Generator
    {
        $document = $this->documentStore->get($uri);

        foreach ($entries as $entry) {
            $entry->sourceUri = $uri;
        }

        $document->set(self::KEY, $entries ?: null);

        return;
        yield;
    }

    public function getFileTimestamps(Uri $filterUri = null): \Generator
    {
        $timestamps = [];
        /** @var Document $document */
        foreach (yield $this->documentStore->getDocumentsForProject($this->project) as $document) {
            if ($document->get(self::KEY) !== null &&
                ($filterUri === null
                || $filterUri->equals($document->getUri())
                || $filterUri->isParentOf($document->getUri()))
            ) {
                $timestamps[$document->getUri()->getNormalized()] = null;
            }
        }

        return $timestamps;
        yield;
    }
}
