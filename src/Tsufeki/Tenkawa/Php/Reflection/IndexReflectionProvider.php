<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\Project;
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
     * @param IndexEntry[] $entries
     *
     * @resolve Element[]
     */
    private function process(array $entries): \Generator
    {
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

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve ClassLike[]
     */
    public function getClass($documentOrProject, string $fullyQualifiedName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CLASS;
        $query->key = '\\' . ltrim($fullyQualifiedName, '\\');

        return yield $this->process(yield $this->index->search($documentOrProject, $query));
    }

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve Function_[]
     */
    public function getFunction($documentOrProject, string $fullyQualifiedName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_FUNCTION;
        $query->key = '\\' . ltrim($fullyQualifiedName, '\\');

        return yield $this->process(yield $this->index->search($documentOrProject, $query));
    }

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve Const_[]
     */
    public function getConst($documentOrProject, string $fullyQualifiedName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CONST;
        $query->key = '\\' . ltrim($fullyQualifiedName, '\\');

        return yield $this->process(yield $this->index->search($documentOrProject, $query));
    }

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve ClassLike[]
     */
    public function getClassesFromUri($documentOrProject, Uri $uri): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CLASS;
        $query->uri = $uri;

        return yield $this->process(yield $this->index->search($documentOrProject, $query));
    }

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve Function_[]
     */
    public function getFunctionsFromUri($documentOrProject, Uri $uri): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_FUNCTION;
        $query->uri = $uri;

        return yield $this->process(yield $this->index->search($documentOrProject, $query));
    }

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve Const_[]
     */
    public function getConstsFromUri($documentOrProject, Uri $uri): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CONST;
        $query->uri = $uri;

        return yield $this->process(yield $this->index->search($documentOrProject, $query));
    }

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve ClassLike[]
     */
    public function getClassesByShortName($documentOrProject, string $shortName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CLASS;
        $query->key = '\\' . ltrim($shortName, '\\');
        $query->match = Query::SUFFIX;

        return yield $this->process(yield $this->index->search($documentOrProject, $query));
    }

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve Function_[]
     */
    public function getFunctionsByShortName($documentOrProject, string $shortName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_FUNCTION;
        $query->key = '\\' . ltrim($shortName, '\\');
        $query->match = Query::SUFFIX;

        return yield $this->process(yield $this->index->search($documentOrProject, $query));
    }

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve Const_[]
     */
    public function getConstsByShortName($documentOrProject, string $shortName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CONST;
        $query->key = '\\' . ltrim($shortName, '\\');
        $query->match = Query::SUFFIX;

        return yield $this->process(yield $this->index->search($documentOrProject, $query));
    }

    /**
     * Get class-likes extending, implementing or using (for traits) given class-like.
     *
     * @param Document|Project $documentOrProject
     *
     * @resolve string[]
     */
    public function getInheritingClasses($documentOrProject, string $fullyQualifiedName): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_INHERITS;
        $query->key = '\\' . ltrim($fullyQualifiedName, '\\');

        $entries = yield $this->index->search($documentOrProject, $query);

        return array_values(array_unique(array_map(function (IndexEntry $entry) {
            return $entry->data;
        }, $entries)));
    }

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve string[]
     */
    public function getAllClassNames($documentOrProject): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CLASS;
        $query->includeData = false;

        $entries = yield $this->index->search($documentOrProject, $query, true);

        return array_map(function (IndexEntry $entry) {
            return $entry->key;
        }, $entries);
    }

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve string[]
     */
    public function getAllFunctionNames($documentOrProject): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_FUNCTION;
        $query->includeData = false;

        $entries = yield $this->index->search($documentOrProject, $query, true);

        return array_map(function (IndexEntry $entry) {
            return $entry->key;
        }, $entries);
    }

    /**
     * @param Document|Project $documentOrProject
     *
     * @resolve string[]
     */
    public function getAllConstNames($documentOrProject): \Generator
    {
        $query = new Query();
        $query->category = ReflectionIndexDataProvider::CATEGORY_CONST;
        $query->includeData = false;

        $entries = yield $this->index->search($documentOrProject, $query, true);

        return array_map(function (IndexEntry $entry) {
            return $entry->key;
        }, $entries);
    }
}
