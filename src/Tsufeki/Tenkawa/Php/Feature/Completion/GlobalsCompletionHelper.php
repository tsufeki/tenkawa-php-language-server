<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionIndexDataProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItem;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItemKind;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionList;
use Tsufeki\Tenkawa\Server\Index\Index;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class GlobalsCompletionHelper
{
    /**
     * @var Index
     */
    private $index;

    public function __construct(Index $index)
    {
        $this->index = $index;
    }

    /**
     * @param string[] $namePartsBeforeCursor
     * @param string[] $namePartsAfterCursor  Includes part the cursor is on.
     * @param int[]    $kinds                 See CompletionKind.
     */
    public function getCompletions(
        Document $document,
        array $namePartsBeforeCursor,
        array $namePartsAfterCursor,
        array $kinds,
        bool $absolute,
        NameContext $nameContext
    ): \Generator {
        $completions = new CompletionList();

        /** @var CompletionItem[][] $items */
        $items = [];
        foreach ($kinds as $kind) {
            if (empty($namePartsBeforeCursor)) {
                if ($absolute) {
                    $items[] = yield $this->searchNamespace('\\', $kind, $document);
                } else {
                    $items[] = yield $this->searchNamespace($nameContext->namespace, $kind, $document);
                    $items[] = $this->getAliases($nameContext, $kind);
                    if (
                        in_array($kind, [CompletionItemKind::CONSTANT, CompletionItemKind::FUNCTION_], true)
                        && $nameContext->namespace !== '\\'
                    ) {
                        $items[] = yield $this->searchNamespace('\\', $kind, $document);
                    }
                }
            } else {
                $nameParts = array_merge($namePartsBeforeCursor, $namePartsAfterCursor);
                $resolvedParts = explode('\\', $nameContext->resolveClass(implode('\\', $nameParts)));
                $prefix = array_slice($resolvedParts, 0, count($resolvedParts) - count($namePartsAfterCursor));
                $items[] = yield $this->searchNamespace('\\' . implode('\\', $prefix), $kind, $document);
            }
        }

        $completions->items = array_unique(array_merge(...$items), SORT_REGULAR);

        return $completions;
    }

    /**
     * @resolve CompletionItem[]
     */
    private function searchNamespace(string $namespace, $kind, Document $document): \Generator
    {
        // TODO search case insensitive

        if ($kind === CompletionItemKind::CONSTANT) {
            $names = yield $this->query($namespace, ReflectionIndexDataProvider::CATEGORY_CONST, $document);
        } elseif ($kind === CompletionItemKind::FUNCTION_) {
            $names = yield $this->query($namespace, ReflectionIndexDataProvider::CATEGORY_FUNCTION, $document);
        } elseif ($kind === CompletionItemKind::CLASS_) {
            $names = yield $this->query($namespace, ReflectionIndexDataProvider::CATEGORY_CLASS, $document);
        } else {
            $names = array_merge(
                yield $this->query($namespace, ReflectionIndexDataProvider::CATEGORY_CONST, $document, true),
                yield $this->query($namespace, ReflectionIndexDataProvider::CATEGORY_FUNCTION, $document, true),
                yield $this->query($namespace, ReflectionIndexDataProvider::CATEGORY_CLASS, $document, true)
            );
        }

        $items = [];
        foreach ($names as $name => $isNamespace) {
            $items[] = $this->makeItem($name, $isNamespace ? CompletionItemKind::MODULE : $kind);
        }

        return $items;
    }

    private function query(
        string $namespace,
        string $category,
        Document $document,
        bool $namespaceOnly = false
    ): \Generator {
        $namespace = rtrim($namespace, '\\') . '\\';

        $query = new Query();
        $query->category = $category;
        $query->key = $namespace;
        $query->match = Query::PREFIX;
        $query->includeData = false;

        /** @var IndexEntry[] $entries */
        $entries = yield $this->index->search($document, $query);
        $namespaceLength = strlen($namespace);
        $names = [];
        foreach ($entries as $entry) {
            $backslashPos = strpos($entry->key, '\\', $namespaceLength);
            if ($backslashPos !== false) {
                $names[substr($entry->key, 0, $backslashPos)] = true;
            } elseif (!$namespaceOnly) {
                $names[$entry->key] = false;
            }
        }

        return $names;
    }

    /**
     * @return CompletionItem[]
     */
    private function getAliases(NameContext $nameContext, $kind): array
    {
        if ($kind === CompletionItemKind::CONSTANT) {
            $uses = $nameContext->constUses;
        } elseif ($kind === CompletionItemKind::FUNCTION_) {
            $uses = $nameContext->functionUses;
        } else {
            $uses = $nameContext->uses;
        }

        $items = [];
        foreach ($uses as $alias => $fullName) {
            $items[] = $this->makeItem($fullName, $kind, $alias);
        }

        return $items;
    }

    private function makeItem(string $name, int $kind, string $shortName = null): CompletionItem
    {
        $shortName = $shortName ?? StringUtils::getShortName($name);

        $item = new CompletionItem();
        $item->label = $shortName;
        $item->kind = $kind === CompletionItemKind::CONSTANT ? CompletionItemKind::VARIABLE : $kind;
        $item->detail = $name;
        $item->insertText = $shortName . ($kind === CompletionItemKind::MODULE ? '\\' : '');

        return $item;
    }
}
