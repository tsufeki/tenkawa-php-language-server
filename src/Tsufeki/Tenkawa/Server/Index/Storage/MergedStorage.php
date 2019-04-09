<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index\Storage;

use Tsufeki\Tenkawa\Server\Index\Query;
use Tsufeki\Tenkawa\Server\Uri;

/**
 * Index data merged from other storage objects.
 */
class MergedStorage implements IndexStorage
{
    /**
     * @var IndexStorage[]
     */
    private $innerStorage = [];

    /**
     * @param IndexStorage[] $innerStorage
     */
    public function __construct(array $innerStorage)
    {
        $this->innerStorage = $innerStorage;
    }

    public function search(Query $query): \Generator
    {
        $result = [];

        foreach ($this->innerStorage as $storage) {
            $result = array_merge($result, yield $storage->search($query));
        }

        return $result;
    }

    public function getFileStamps(?Uri $filterUri = null): \Generator
    {
        $result = [];

        foreach ($this->innerStorage as $storage) {
            $result = array_merge($result, yield $storage->getFileStamps($filterUri));
        }

        return $result;
    }
}
