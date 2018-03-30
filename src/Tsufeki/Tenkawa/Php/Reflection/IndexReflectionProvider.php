<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\KayoJsonMapper\Mapper;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Index\Index;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;
use Tsufeki\Tenkawa\Server\Uri;

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

    /**
     * @var ReflectionTransformer[]
     */
    private $transformers;

    /**
     * @param ReflectionTransformer[] $transformers
     */
    public function __construct(Index $index, Mapper $mapper, array $transformers)
    {
        $this->index = $index;
        $this->mapper = $mapper;
        $this->transformers = $transformers;
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

        $elements = [];
        foreach ($entries as $entry) {
            $element = $this->mapper->load($entry->data, $itemClass);
            foreach ($this->transformers as $transformer) {
                $element = yield $transformer->transform($element);
            }
            $elements[] = $element;
        }

        return $elements;
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
