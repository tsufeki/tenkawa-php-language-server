<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index\Storage;

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

    public function search(string $category = null, string $key, int $match = self::FULL): \Generator
    {
        $result = [];

        foreach ($this->innerStorage as $storage) {
            $result = array_merge($result, yield $storage->search($category, $key, $match));
        }

        return $result;
    }

    public function getFileTimestamps(): \Generator
    {
        $result = [];

        foreach ($this->innerStorage as $storage) {
            $result = array_merge($result, yield $storage->getFileTimestamps());
        }

        return $result;
    }
}
