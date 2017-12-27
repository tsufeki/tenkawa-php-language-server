<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index\Storage;

use Tsufeki\Tenkawa\Uri;
use Tsufeki\Tenkawa\Utils\StringUtils;

class MemoryStorage implements IndexStorage
{
    /**
     * @var IndexEntry[]
     */
    private $entries = [];

    public function search(string $category = null, string $key, int $match = self::FULL): \Generator
    {
        $result = [];

        foreach ($this->entries as $entry) {
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

        return $result;
        yield;
    }

    public function add(IndexEntry $entry): \Generator
    {
        $this->entries[] = $entry;

        return;
        yield;
    }

    public function purgeFile(Uri $uri): \Generator
    {
        foreach ($this->entries as $i => $entry) {
            if ($entry->sourceUri == $uri) {
                unset($this->entries[$i]);
            }
        }

        return;
        yield;
    }

    public function setFileTimestamp(Uri $uri, int $timestamp = null): \Generator
    {
        // @codeCoverageIgnoreStart
        return;
        yield;
        // @codeCoverageIgnoreEnd
    }

    public function getFileTimestamps(): \Generator
    {
        $result = [];

        foreach ($this->entries as $entry) {
            $result[(string)$entry->sourceUri] = null;
        }

        return $result;
        yield;
    }
}
