<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use Tsufeki\Tenkawa\Php\Feature\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Namespace_;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionIndexDataProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItem;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItemKind;
use Tsufeki\Tenkawa\Server\Index\Index;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class GlobalSymbolCompleter implements SymbolCompleter
{
    /**
     * @var Index
     */
    private $index;

    const INDEX_CATEGORIES = [
        GlobalSymbol::CLASS_ => ReflectionIndexDataProvider::CATEGORY_CLASS,
        GlobalSymbol::FUNCTION_ => ReflectionIndexDataProvider::CATEGORY_FUNCTION,
        GlobalSymbol::CONST_ => ReflectionIndexDataProvider::CATEGORY_CONST,
    ];

    const COMPLETION_KINDS = [
        GlobalSymbol::CLASS_ => CompletionItemKind::CLASS_,
        GlobalSymbol::FUNCTION_ => CompletionItemKind::FUNCTION_,
        GlobalSymbol::CONST_ => CompletionItemKind::VARIABLE,
        GlobalSymbol::NAMESPACE_ => CompletionItemKind::MODULE,
    ];

    const SEARCH_KINDS = [
        GlobalSymbol::CLASS_ => [GlobalSymbol::CLASS_],
        GlobalSymbol::FUNCTION_ => [GlobalSymbol::FUNCTION_],
        GlobalSymbol::CONST_ => [ // const fetch may be partial function or class
            GlobalSymbol::CLASS_,
            GlobalSymbol::FUNCTION_,
            GlobalSymbol::CONST_,
        ],
        GlobalSymbol::NAMESPACE_ => [
            GlobalSymbol::CLASS_,
            GlobalSymbol::FUNCTION_,
            GlobalSymbol::CONST_,
        ],
    ];

    public function __construct(Index $index)
    {
        $this->index = $index;
    }

    public function getTriggerCharacters(): array
    {
        return ['\\'];
    }

    /**
     * @resolve CompletionItem[]
     */
    public function getCompletions(Symbol $symbol, Position $position): \Generator
    {
        if (!($symbol instanceof GlobalSymbol)) {
            return [];
        }

        list($namespaces, $isUnqualified) = $this->getNamespaces($symbol, $position);
        $items = [];
        foreach ($namespaces as $namespace) {
            $items = array_merge($items, yield $this->query(
                $namespace,
                self::SEARCH_KINDS[$symbol->kind] ?? [],
                $symbol->document,
                $symbol->kind === GlobalSymbol::NAMESPACE_
            ));
        }

        if ($isUnqualified &&
            $symbol->kind !== GlobalSymbol::NAMESPACE_ &&
            !$symbol->isImport
        ) {
            $items = array_merge($items, yield $this->getAliases(
                $symbol->nameContext,
                self::SEARCH_KINDS[$symbol->kind] ?? []
            ));
        }

        return $items;
    }

    private function getNamespaces(GlobalSymbol $symbol, Position $position): array
    {
        $offset = PositionUtils::offsetFromPosition($position, $symbol->document);
        $startOffset = PositionUtils::offsetFromPosition($symbol->range->start, $symbol->document);
        $offsetWithinName = $offset - $startOffset;

        $beforeCount = substr_count($symbol->originalName, '\\', 0, $offsetWithinName);
        $afterCount = substr_count($symbol->originalName, '\\') + 1 - $beforeCount;
        $isUnqualified = $beforeCount === 0;

        if (substr($symbol->originalName, -1) === '\\') {
            $afterCount--;
        }

        $namespaces = [];
        foreach ($symbol->referencedNames as $name) {
            $parts = explode('\\', $name);
            $parts = array_slice($parts, 0, count($parts) - $afterCount);
            $namespaces[] = implode('\\', $parts);
        }

        return [$namespaces, $isUnqualified];
    }

    /**
     * @resolve CompletionItem[]
     */
    private function query(
        string $namespace,
        array $kinds,
        Document $document,
        bool $namespaceOnly = false
    ): \Generator {
        $namespace = rtrim($namespace, '\\') . '\\';

        /** @var CompletionItem[] $namespaces */
        $namespaces = [];
        /** @var CompletionItem[] $elements */
        $elements = [];

        // TODO search case insensitive
        $query = new Query();
        $query->key = $namespace;
        $query->match = Query::PREFIX;
        $query->includeData = false;

        foreach ($kinds as $kind) {
            $query->category = self::INDEX_CATEGORIES[$kind];

            /** @var IndexEntry[] $entries */
            $entries = yield $this->index->search($document, $query);
            $namespaceLength = strlen($namespace);
            foreach ($entries as $entry) {
                $backslashPos = strpos($entry->key, '\\', $namespaceLength);
                if ($backslashPos !== false) {
                    $nsName = substr($entry->key, 0, $backslashPos);
                    if (!isset($namespaces[$nsName])) {
                        $namespaces[$nsName] = $this->makeItem($nsName, GlobalSymbol::NAMESPACE_);
                    }
                } elseif (!$namespaceOnly) {
                    $elements[] = $this->makeItem($entry->key, $kind);
                }
            }
        }

        return array_merge(array_values($namespaces), $elements);
    }

    /**
     * @resolve CompletionItem[]
     */
    private function getAliases(NameContext $nameContext, array $kinds): \Generator
    {
        $items = [];
        foreach ($kinds as $kind) {
            $uses = [];
            if ($kind === GlobalSymbol::CLASS_) {
                $uses = $nameContext->uses;
            } elseif ($kind === GlobalSymbol::FUNCTION_) {
                $uses = $nameContext->functionUses;
            } elseif ($kind === GlobalSymbol::CONST_) {
                $uses = $nameContext->constUses;
            }

            foreach ($uses as $alias => $fullName) {
                // TODO distinguish between classes and namespaces
                $items[] = $this->makeItem($fullName, $kind);
            }
        }

        return $items;
        yield;
    }

    private function makeItem(string $name, $kind): CompletionItem
    {
        $shortName = StringUtils::getShortName($name);

        $item = new CompletionItem();
        $item->label = $shortName;
        $item->kind = self::COMPLETION_KINDS[$kind];
        $item->detail = $name;
        $item->insertText = $shortName;

        if ($kind === GlobalSymbol::NAMESPACE_) {
            $item->insertText .= '\\';
        } elseif ($kind === GlobalSymbol::FUNCTION_) {
            $item->insertText .= '(';
        }

        return $item;
    }
}
