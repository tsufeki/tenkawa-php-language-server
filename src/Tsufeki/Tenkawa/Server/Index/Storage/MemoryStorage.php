<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index\Storage;

use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class MemoryStorage implements WritableIndexStorage
{
    /**
     * @var array<string,array<string,IndexEntry[]>> uri => category => entries
     */
    private $entries = [];

    /**
     * @var array<string,string|null>
     */
    private $stamps = [];

    public function search(Query $query): \Generator
    {
        $result = [];
        $entries = $query->uri === null ? $this->entries : [$this->entries[$query->uri->getNormalized()] ?? []];

        foreach ($entries as $entriesByCategory) {
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

    public function replaceFile(Uri $uri, array $entries, ?string $stamp): \Generator
    {
        $uriString = $uri->getNormalized();
        unset($this->entries[$uriString]);
        unset($this->stamps[$uriString]);

        foreach ($entries as $entry) {
            $entry->sourceUri = $uri;
            $this->entries[$uriString][$entry->category][] = $entry;
        }

        if (!empty($entries)) {
            $this->stamps[$uriString] = $stamp;
        }

        return;
        yield;
    }

    public function getFileStamps(?Uri $filterUri = null): \Generator
    {
        if ($filterUri === null) {
            return $this->stamps;
        }

        $result = [];
        foreach ($this->stamps as $uriString => $stamp) {
            $uri = Uri::fromString($uriString);
            if ($filterUri->equals($uri) || $filterUri->isParentOf($uri)) {
                $result[$uriString] = $stamp;
            }
        }

        return $result;
        yield;
    }
}
