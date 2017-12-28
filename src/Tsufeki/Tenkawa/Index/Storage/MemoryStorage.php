<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index\Storage;

use Tsufeki\Tenkawa\Index\IndexEntry;
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

    public function search(string $category = null, string $key, int $match = self::FULL): \Generator
    {
        $result = [];

        foreach ($this->entries as $fileEntries) {
            foreach ($fileEntries as $entry) {
                if ($category !== null && $entry->category !== $category) {
                    continue;
                }

                if ($match === self::FULL && $entry->key !== $key) {
                    continue;
                }

                if ($match === self::PREFIX && !StringUtils::startsWith($entry->key, $key)) {
                    continue;
                }

                if ($match === self::SUFFIX && !StringUtils::endsWith($entry->key, $key)) {
                    continue;
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
