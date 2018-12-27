<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index\Storage;

use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class MemoryStorage implements WritableIndexStorage
{
    /**
     * @var array<string,IndexEntry[]>
     */
    private $entries = [];

    /**
     * @var array<string,int|null>
     */
    private $timestamps = [];

    public function search(Query $query): \Generator
    {
        $result = [];
        $entries = $query->uri === null ? $this->entries : [$this->entries[$query->uri->getNormalized()] ?? []];

        foreach ($entries as $fileEntries) {
            foreach ($fileEntries as $entry) {
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

                if ($query->tag !== null && !in_array($entry->tag, $query->tag, true)) {
                    continue;
                }

                $result[] = $entry;
            }
        }

        return $result;
        yield;
    }

    public function replaceFile(Uri $uri, array $entries, ?int $timestamp): \Generator
    {
        $uriString = $uri->getNormalized();
        unset($this->entries[$uriString]);
        unset($this->timestamps[$uriString]);

        foreach ($entries as $entry) {
            $entry->sourceUri = $uri;
            $this->entries[$uriString][] = $entry;
        }

        if (!empty($entries)) {
            $this->timestamps[$uriString] = $timestamp;
        }

        return;
        yield;
    }

    public function getFileTimestamps(?Uri $filterUri = null): \Generator
    {
        if ($filterUri === null) {
            return $this->timestamps;
        }

        $result = [];
        foreach ($this->timestamps as $uriString => $timestamp) {
            $uri = Uri::fromString($uriString);
            if ($filterUri->equals($uri) || $filterUri->isParentOf($uri)) {
                $result[$uriString] = $timestamp;
            }
        }

        return $result;
        yield;
    }
}
