<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Reflection;

use Tsufeki\KayoJsonMapper\Mapper;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Index\Index;
use Tsufeki\Tenkawa\Index\IndexEntry;
use Tsufeki\Tenkawa\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Reflection\Element\Function_;

class IndexReflectionProvider implements ReflectionProvider
{
    /**
     * @var Index
     */
    private $index;

    /**
     * @var Mapper
     */
    private $mapper;

    public function __construct(Index $index, Mapper $mapper)
    {
        $this->index = $index;
        $this->mapper = $mapper;
    }

    private function getFromIndex(
        Document $document,
        string $category,
        string $fullyQualifiedName,
        string $itemClass
    ): \Generator {
        $fullyQualifiedName = '\\' . ltrim($fullyQualifiedName, '\\');

        /** @var IndexEntry[] $entries */
        $entries = yield $this->index->search($document, $category, $fullyQualifiedName);

        return array_map(function (IndexEntry $entry) use ($itemClass) {
            return $this->mapper->load($entry->data, $itemClass);
        }, $entries);
    }

    public function getClass(Document $document, string $fullyQualifiedName): \Generator
    {
        return yield $this->getFromIndex(
            $document,
            ReflectionIndexDataProvider::CATEGORY_CLASS,
            strtolower($fullyQualifiedName),
            ClassLike::class
        );
    }

    public function getFunction(Document $document, string $fullyQualifiedName): \Generator
    {
        return yield $this->getFromIndex(
            $document,
            ReflectionIndexDataProvider::CATEGORY_FUNCTION,
            strtolower($fullyQualifiedName),
            Function_::class
        );
    }

    public function getConst(Document $document, string $fullyQualifiedName): \Generator
    {
        return yield $this->getFromIndex(
            $document,
            ReflectionIndexDataProvider::CATEGORY_CONST,
            $fullyQualifiedName,
            Const_::class
        );
    }
}
