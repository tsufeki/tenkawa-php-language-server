<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index\Storage;

use Tsufeki\Tenkawa\Index\IndexEntry;

/**
 * Index data from primary storage plus data from secondary, but only that missing in primary.
 *
 * For performance reasons, primary storage should be small.
 */
class ChainedStorage implements IndexStorage
{
    /**
     * @var IndexStorage
     */
    private $primaryStorage;

    /**
     * @var IndexStorage
     */
    private $secondaryStorage;

    public function __construct(IndexStorage $primaryStorage, IndexStorage $secondaryStorage)
    {
        $this->primaryStorage = $primaryStorage;
        $this->secondaryStorage = $secondaryStorage;
    }

    public function search(string $category = null, string $key, int $match = self::FULL): \Generator
    {
        $result = yield $this->primaryStorage->search($category, $key, $match);
        $primaryFiles = yield $this->primaryStorage->getFileTimestamps();

        /** @var IndexEntry $entry */
        foreach (yield $this->secondaryStorage->search($category, $key, $match) as $entry) {
            if (!array_key_exists((string)$entry->sourceUri, $primaryFiles)) {
                $result[] = $entry;
            }
        }

        return $result;
    }

    public function getFileTimestamps(): \Generator
    {
        return array_merge(
            yield $this->secondaryStorage->getFileTimestamps(),
            yield $this->primaryStorage->getFileTimestamps()
        );
    }
}
