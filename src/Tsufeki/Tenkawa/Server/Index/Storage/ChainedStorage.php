<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index\Storage;

use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;

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

    public function search(Query $query): \Generator
    {
        $result = yield $this->primaryStorage->search($query);
        $primaryFiles = yield $this->primaryStorage->getFileTimestamps();

        /** @var IndexEntry $entry */
        foreach (yield $this->secondaryStorage->search($query) as $entry) {
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
