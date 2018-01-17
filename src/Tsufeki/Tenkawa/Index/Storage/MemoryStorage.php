<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index\Storage;

use Tsufeki\Tenkawa\Index\IndexEntry;
use Tsufeki\Tenkawa\Index\Query;
use Tsufeki\Tenkawa\Uri;
use Tsufeki\Tenkawa\Utils\StringUtils;

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
        $entries = $query->uri === null ? $this->entries : [$this->entries[(string)$query->uri] ?? []];

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

                $result[] = $entry;
            }
        }

        return $result;
        yield;
    }

    public function replaceFile(Uri $uri, array $entries, int $timestamp = null): \Generator
    {
        $uriString = (string)$uri;
        unset($this->entries[$uriString]);
        unset($this->timestamps[$uriString]);

        foreach ($entries as $entry) {
            $entry->sourceUri = $uri;
            $this->entries[$uriString][] = $entry;
        }

        $this->timestamps[$uriString] = $timestamp;

        return;
        yield;
    }

    public function getFileTimestamps(): \Generator
    {
        return $this->timestamps;
        yield;
    }
}
