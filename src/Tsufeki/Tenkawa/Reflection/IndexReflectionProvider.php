<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Reflection;

use Tsufeki\KayoJsonMapper\Mapper;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Index\Index;
use Tsufeki\Tenkawa\Index\IndexEntry;
use Tsufeki\Tenkawa\Index\Query;
use Tsufeki\Tenkawa\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Uri;

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
        string $itemClass,
        string $fullyQualifiedName = null,
        Uri $uri = null
    ): \Generator {
        $fullyQualifiedName = $fullyQualifiedName ? '\\' . ltrim($fullyQualifiedName, '\\') : null;
        $query = new Query();
        $query->category = $category;
        $query->key = $fullyQualifiedName;
        $query->uri = $uri;

        /** @var IndexEntry[] $entries */
        $entries = yield $this->index->search($document, $query);

        return array_map(function (IndexEntry $entry) use ($itemClass) {
            return $this->mapper->load($entry->data, $itemClass);
        }, $entries);
    }

    public function getClass(Document $document, string $fullyQualifiedName): \Generator
    {
        return yield $this->getFromIndex(
            $document,
            ReflectionIndexDataProvider::CATEGORY_CLASS,
            ClassLike::class,
            $fullyQualifiedName
        );
    }

    public function getFunction(Document $document, string $fullyQualifiedName): \Generator
    {
        return yield $this->getFromIndex(
            $document,
            ReflectionIndexDataProvider::CATEGORY_FUNCTION,
            Function_::class,
            $fullyQualifiedName
        );
    }

    public function getConst(Document $document, string $fullyQualifiedName): \Generator
    {
        return yield $this->getFromIndex(
            $document,
            ReflectionIndexDataProvider::CATEGORY_CONST,
            Const_::class,
            $fullyQualifiedName
        );
    }

    public function getClassesFromUri(Document $document, Uri $uri): \Generator
    {
        return yield $this->getFromIndex(
            $document,
            ReflectionIndexDataProvider::CATEGORY_CLASS,
            ClassLike::class,
            null,
            $uri
        );
    }

    public function getFunctionsFromUri(Document $document, Uri $uri): \Generator
    {
        return yield $this->getFromIndex(
            $document,
            ReflectionIndexDataProvider::CATEGORY_FUNCTION,
            Function_::class,
            null,
            $uri
        );
    }

    public function getConstsFromUri(Document $document, Uri $uri): \Generator
    {
        return yield $this->getFromIndex(
            $document,
            ReflectionIndexDataProvider::CATEGORY_CONST,
            Const_::class,
            null,
            $uri
        );
    }
}
