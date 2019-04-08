<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Namespace_;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\NameHelper;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionIndexDataProvider;
use Tsufeki\Tenkawa\Php\Symbol\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Symbol\Symbol;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItem;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItemKind;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionList;
use Tsufeki\Tenkawa\Server\Feature\Configuration\ConfigurationFeature;
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
        GlobalSymbol::CLASS_,
        GlobalSymbol::FUNCTION_,
        GlobalSymbol::CONST_,
    ];

    /**
     * @var ConfigurationFeature
     */
    private $configurationFeature;

    /**
     * @var string[]
     */
    private $defaultExtensions;

    const ITEM_LIMIT = 100;

    /**
     * @param string[] $defaultExtensions
     */
    public function __construct(array $defaultExtensions, Index $index, ConfigurationFeature $configurationFeature)
    {
        $this->defaultExtensions = $defaultExtensions;
        $this->index = $index;
        $this->configurationFeature = $configurationFeature;
    }

    public function getTriggerCharacters(): array
    {
        return ['\\'];
    }

    /**
     * @resolve CompletionList
     */
    public function getCompletions(Symbol $symbol, Position $position): \Generator
    {
        $completions = new CompletionList();
        if (!($symbol instanceof GlobalSymbol)) {
            return $completions;
        }

        [$before, $after] = $this->splitName($symbol, $position);
        $unqualified = empty($before);
        $addTrailingParen = false;
        $addTrailingBackslash = true;
        $kinds = [$symbol->kind];

        if (count($after) > 1) {
            $kinds = [GlobalSymbol::NAMESPACE_];
            $addTrailingBackslash = false;
        }

        if ($kinds === [GlobalSymbol::CONST_] && !$symbol->isImport) {
            $kinds = self::SEARCH_KINDS;
            $addTrailingParen = true;
        }

        if (!in_array(GlobalSymbol::NAMESPACE_, $kinds, true)) {
            $kinds[] = GlobalSymbol::NAMESPACE_;
        }

        $result = [];
        $namespace = implode('\\', $before) ?: '\\';

        if ($unqualified) {
            $result = yield $this->getAliases($symbol->nameContext, $kinds);
            $namespace = $symbol->nameContext->namespace;
        }

        yield $this->query($namespace, $kinds, $after[0] ?? null, $symbol->document, $result);

        if ($unqualified) {
            $kinds = array_diff($kinds, [GlobalSymbol::CLASS_, GlobalSymbol::NAMESPACE_]);
            yield $this->query('\\', $kinds, $after[0] ?? null, $symbol->document, $result);
        }

        $completions->isIncomplete = true;
        foreach ($result as $kind => $kindResult) {
            foreach ($kindResult as $shortName => $fullName) {
                $completions->items[] = $this->makeItem($fullName, $kind, $shortName, $addTrailingBackslash, $addTrailingParen);
            }
        }

        return $completions;
    }

    private function splitName(GlobalSymbol $symbol, Position $position): array
    {
        $offset = PositionUtils::offsetFromPosition($position, $symbol->document);
        $startOffset = PositionUtils::offsetFromPosition($symbol->range->start, $symbol->document);
        $offsetWithinName = $offset - $startOffset;

        $beforeCount = substr_count($symbol->originalName, '\\', 0, $offsetWithinName);

        $parts = explode('\\', $symbol->originalName);
        $before = array_slice($parts, 0, $beforeCount);
        $after = array_slice($parts, $beforeCount);

        $part = $after[0];
        $beforeLength = array_sum(array_map('strlen', $before)) + count($before);
        $offsetWithinPart = $offsetWithinName - $beforeLength;
        $leadingWhiteSpace = strlen($part) - strlen(ltrim($part));
        $trailingWhiteSpace = strlen($part) - strlen(rtrim($part));

        // Treat whitespace as separators, this should give better results on
        // partial input (it won't glue unrelated tokens from the next line, etc.)
        if ($leadingWhiteSpace > 0 && $offsetWithinPart >= $leadingWhiteSpace) {
            $before = [];
        }
        if ($trailingWhiteSpace > 0) {
            $after = [$after[0]];
        }

        if (count($before) !== 0 || $symbol->isImport || $symbol->kind === GlobalSymbol::NAMESPACE_) {
            $resolvedParts = explode('\\', $symbol->referencedNames[0]);
            $resolvedCount = count($resolvedParts) - count($after);
            if (end($after) === '' && end($resolvedParts) !== '') {
                $resolvedCount++;
            }
            $before = array_slice($resolvedParts, 0, $resolvedCount);
        }

        return [$before, array_map('trim', $after)];
    }

    private function query(
        string $namespace,
        array $kinds,
        ?string $fuzzyQuery,
        Document $document,
        array &$result
    ): \Generator {
        if (empty($kinds)) {
            return;
        }

        // TODO search case insensitive
        $namespace = rtrim($namespace, '\\') . '\\';
        $query = new Query();
        $query->key = $namespace;
        $query->match = Query::PREFIX;
        $query->fuzzy = $fuzzyQuery;
        $query->fuzzySeparator = '\\';
        $query->limit = self::ITEM_LIMIT;
        $query->includeData = false;
        $query->tag = yield $this->getTags($document);

        $withNamespaces = in_array(GlobalSymbol::NAMESPACE_, $kinds, true);
        $withElements = true;
        $kinds = array_diff($kinds, [GlobalSymbol::NAMESPACE_]);
        if ($kinds === []) {
            $withElements = false;
            $kinds = self::SEARCH_KINDS;
        }

        foreach ($kinds as $kind) {
            $query->category = self::INDEX_CATEGORIES[$kind];

            /** @var IndexEntry[] $entries */
            $entries = yield $this->index->search($document, $query);
            $namespaceLength = strlen($namespace);
            foreach ($entries as $entry) {
                if (NameHelper::isSpecial($entry->key)) {
                    continue;
                }

                $itemFullName = null;
                $itemKind = null;
                $backslashPos = strpos($entry->key, '\\', $namespaceLength);
                if ($backslashPos !== false && $withNamespaces) {
                    $itemFullName = substr($entry->key, 0, $backslashPos);
                    $itemKind = GlobalSymbol::NAMESPACE_;
                } elseif ($backslashPos === false && $withElements) {
                    $itemFullName = $entry->key;
                    $itemKind = $kind;
                }

                $itemShortName = StringUtils::getShortName($itemFullName ?: '');
                if ($itemFullName !== null && !isset($result[$itemKind][$itemShortName])) {
                    $result[$itemKind][$itemShortName] = $itemFullName;
                }
            }
        }
    }

    private function getTags(Document $document): \Generator
    {
        $extensions = (yield $this->configurationFeature->get('completion.extensions', $document)) ?? [];
        $extensions = array_values(array_unique(array_merge($this->defaultExtensions, $extensions)));
        $extensions = array_map(function (string $ext) { return strtolower("ext:$ext"); }, $extensions);
        $extensions[] = null;

        return $extensions;
    }

    private function getAliases(NameContext $nameContext, array $kinds): \Generator
    {
        $items = [];
        foreach ($kinds as $kind) {
            $uses = [];
            // TODO distinguish between classes and namespaces
            if ($kind === GlobalSymbol::CLASS_) {
                $uses = $nameContext->uses;
            } elseif ($kind === GlobalSymbol::FUNCTION_) {
                $uses = $nameContext->functionUses;
            } elseif ($kind === GlobalSymbol::CONST_) {
                $uses = $nameContext->constUses;
            }

            foreach ($uses as $alias => $fullName) {
                $items[$kind][$alias] = $fullName;
            }
        }

        return $items;
        yield;
    }

    private function makeItem(
        string $name,
        $kind,
        string $shortName,
        bool $addTrailingBackslash,
        bool $addTrailingParen
    ): CompletionItem {
        $shortName = $shortName ?? StringUtils::getShortName($name);

        $item = new CompletionItem();
        $item->label = $shortName;
        $item->kind = self::COMPLETION_KINDS[$kind];
        $item->detail = $name;
        $item->insertText = $shortName;

        if ($kind === GlobalSymbol::NAMESPACE_ && $addTrailingBackslash) {
            $item->insertText .= '\\';
        } elseif ($kind === GlobalSymbol::FUNCTION_ && $addTrailingParen) {
            $item->insertText .= '(';
        }

        return $item;
    }
}
