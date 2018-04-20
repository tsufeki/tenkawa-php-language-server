<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use Tsufeki\Tenkawa\Php\Feature\ImportEditData;
use Tsufeki\Tenkawa\Php\Feature\ImportHelper;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionIndexDataProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\WorkspaceEdit;
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

    /**
     * @var ImportHelper
     */
    private $importHelper;

    public function __construct(Index $index, ImportHelper $importHelper)
    {
        $this->index = $index;
        $this->importHelper = $importHelper;
    }

    /**
     * @param string[] $namePartsBeforeCursor
     * @param string[] $namePartsAfterCursor  Includes part the cursor is on.
     * @param int[]    $kinds                 See CompletionKind.
     *
     * @resolve CompletionList
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
                if (!$absolute) {
                    $nameParts = explode('\\', ltrim($nameContext->resolveClass(implode('\\', $nameParts)), '\\'));
                }
                $prefix = array_slice($nameParts, 0, count($nameParts) - count($namePartsAfterCursor));
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
        $item->insertText = $shortName;

        if ($kind === CompletionItemKind::MODULE) {
            $item->insertText .= '\\';
        } elseif ($kind === CompletionItemKind::FUNCTION_) {
            $item->insertText .= '(';
        }

        return $item;
    }

    /**
     * @param int[] $kinds
     *
     * @resolve CompletionList
     */
    public function getImportCompletions(
        Document $document,
        Position $position,
        array $kinds,
        NameContext $nameContext
    ): \Generator {
        $completions = new CompletionList();

        /** @var CompletionItem[] $items */
        $items = [];
        /** @var ImportEditData $importEditData */
        $importEditData = yield $this->importHelper->getImportEditData($document, $position);

        foreach ($kinds as $kind) {
            if ($kind === CompletionItemKind::CONSTANT) {
                $names = yield $this->queryImportable(ReflectionIndexDataProvider::CATEGORY_CONST, $document);
            } elseif ($kind === CompletionItemKind::FUNCTION_) {
                $names = yield $this->queryImportable(ReflectionIndexDataProvider::CATEGORY_FUNCTION, $document);
            } elseif ($kind === CompletionItemKind::CLASS_) {
                $names = yield $this->queryImportable(ReflectionIndexDataProvider::CATEGORY_CLASS, $document);
            } else {
                continue;
            }

            foreach ($names as $name) {
                if ($this->filterImportable($name, $kind, $nameContext)) {
                    $items[] = yield $this->makeImportItem(
                        $name,
                        $kind,
                        $document,
                        $importEditData
                    );
                }
            }
        }

        $completions->items = array_unique($items, SORT_REGULAR);

        return $completions;
    }

    /**
     * @resolve string[]
     */
    private function queryImportable(
        string $category,
        Document $document
    ): \Generator {
        $query = new Query();
        $query->category = $category;
        $query->key = '';
        $query->match = Query::PREFIX;
        $query->includeData = false;

        /** @var IndexEntry[] $entries */
        $entries = yield $this->index->search($document, $query);
        $names = [];
        foreach ($entries as $entry) {
            $names[] = $entry->key;
        }

        return $names;
    }

    private function filterImportable(string $name, int $kind, NameContext $nameContext): bool
    {
        // Is global?
        $parts = explode('\\', ltrim($name, '\\'));
        if (count($parts) === 1) {
            return false;
        }

        // Is in the same namespace?
        $namespaceParts = explode('\\', ltrim($nameContext->namespace, '\\'));
        if (count($parts) === count($namespaceParts) + 1 &&
            $namespaceParts === array_slice($parts, 0, -1)
        ) {
            return false;
        }

        $importKind = '';
        if ($kind === CompletionItemKind::FUNCTION_) {
            $importKind = 'function';
        } elseif ($kind === CompletionItemKind::CONSTANT) {
            $importKind = 'const';
        }

        return !$this->importHelper->isAlreadyImported(array_slice($parts, -1), $importKind, $nameContext);
    }

    /**
     * @resolve CompletionItem
     */
    private function makeImportItem(string $name, int $kind, Document $document, ImportEditData $data): \Generator
    {
        $shortName = StringUtils::getShortName($name);

        $item = new CompletionItem();
        $item->label = $shortName;
        $item->kind = $kind === CompletionItemKind::CONSTANT ? CompletionItemKind::VARIABLE : $kind;
        $item->detail = 'use ' . ltrim($name, '\\');

        $importKind = '';
        if ($kind === CompletionItemKind::FUNCTION_) {
            $importKind = 'function';
            $item->insertText = $item->label . '(';
        } elseif ($kind === CompletionItemKind::CONSTANT) {
            $importKind = 'const';
        }

        /** @var WorkspaceEdit $workspaceEdit */
        $workspaceEdit = yield $this->importHelper->getImportEditWithData(
            $document,
            $data,
            $importKind,
            $name
        );

        $item->additionalTextEdits = $workspaceEdit->documentChanges[0]->edits;

        return $item;
    }
}
