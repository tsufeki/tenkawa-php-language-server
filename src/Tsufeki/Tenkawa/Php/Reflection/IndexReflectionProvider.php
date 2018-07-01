<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
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
     * @var ReflectionTransformer[]
     */
    private $transformers;

    /**
     * @param ReflectionTransformer[] $transformers
     */
    public function __construct(Index $index, array $transformers)
    {
        $this->index = $index;
        $this->transformers = $transformers;
    }

    /**
     * @resolve Element[]
     */
    private function search(Query $query, Document $document): \Generator
    {
        /** @var IndexEntry[] $entries */
        $entries = yield $this->index->search($document, $query);

        $elements = [];
        foreach ($entries as $entry) {
            $element = $entry->data;
            assert($element instanceof Element);
            foreach ($this->transformers as $transformer) {
                $element = yield $transformer->transform($element);
            }
            $elements[] = $element;
        }

        return $elements;
    }

    public function getClass(Document $document, string $fullyQualifiedName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CLASS;
        $query->key = '\\' . ltrim($fullyQualifiedName, '\\');

        return yield $this->search($query, $document);
    }

    public function getFunction(Document $document, string $fullyQualifiedName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_FUNCTION;
        $query->key = '\\' . ltrim($fullyQualifiedName, '\\');

        return yield $this->search($query, $document);
    }

    public function getConst(Document $document, string $fullyQualifiedName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CONST;
        $query->key = '\\' . ltrim($fullyQualifiedName, '\\');

        return yield $this->search($query, $document);
    }

    public function getClassesFromUri(Document $document, Uri $uri): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CLASS;
        $query->uri = $uri;

        return yield $this->search($query, $document);
    }

    public function getFunctionsFromUri(Document $document, Uri $uri): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_FUNCTION;
        $query->uri = $uri;

        return yield $this->search($query, $document);
    }

    public function getConstsFromUri(Document $document, Uri $uri): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CONST;
        $query->uri = $uri;

        return yield $this->search($query, $document);
    }

    public function getClassesByShortName(Document $document, string $shortName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CLASS;
        $query->key = '\\' . ltrim($shortName, '\\');
        $query->match = Query::SUFFIX;

        return yield $this->search($query, $document);
    }

    public function getFunctionsByShortName(Document $document, string $shortName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_FUNCTION;
        $query->key = '\\' . ltrim($shortName, '\\');
        $query->match = Query::SUFFIX;

        return yield $this->search($query, $document);
    }

    public function getConstsByShortName(Document $document, string $shortName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CONST;
        $query->key = '\\' . ltrim($shortName, '\\');
        $query->match = Query::SUFFIX;

        return yield $this->search($query, $document);
    }

    /**
     * Get class-likes extending, implementing or using (for traits) given class-like.
     *
     * @resolve string[]
     */
    public function getInheritingClasses(Document $document, string $fullyQualifiedName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_INHERITS;
        $query->key = '\\' . ltrim($fullyQualifiedName, '\\');

        $entries = yield $this->index->search($document, $query);

        return array_values(array_unique(array_map(function (IndexEntry $entry) {
            return $entry->data;
        }, $entries)));
    }
}
