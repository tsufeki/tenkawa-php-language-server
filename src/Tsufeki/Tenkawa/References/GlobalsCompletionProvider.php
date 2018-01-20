<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\Node\Name;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Index\Index;
use Tsufeki\Tenkawa\Index\IndexEntry;
use Tsufeki\Tenkawa\Index\Query;
use Tsufeki\Tenkawa\Parser\Ast;
use Tsufeki\Tenkawa\Parser\Parser;
use Tsufeki\Tenkawa\Parser\TokenIterator;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionContext;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionItem;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionItemKind;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionList;
use Tsufeki\Tenkawa\Reflection\NameContext;
use Tsufeki\Tenkawa\Reflection\ReflectionIndexDataProvider;
use Tsufeki\Tenkawa\Utils\PositionUtils;
use Tsufeki\Tenkawa\Utils\StringUtils;

class GlobalsCompletionProvider implements CompletionProvider
{
    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var Index
     */
    private $index;

    public function __construct(Parser $parser, Index $index)
    {
        $this->parser = $parser;
        $this->index = $index;
    }

    public function getCompletions(
        Document $document,
        Position $position,
        CompletionContext $context = null,
        array $nodes
    ): \Generator {
        $completions = new CompletionList();

        if (count($nodes) < 2 || !($nodes[0] instanceof Name)) {
            return $completions;
        }

        /** @var Ast $ast */
        $ast = yield $this->parser->parse($document);
        $offset = PositionUtils::offsetFromPosition($position, $document);
        /** @var Name $node */
        $node = $nodes[0];

        $iterator = TokenIterator::fromNode($node, $ast->tokens);
        $startsWithBackslash = $iterator->valid() && $iterator->getType() === T_NS_SEPARATOR;
        $beforeParts = [];
        $afterParts = [];
        while ($iterator->valid()) {
            if ($iterator->getType() === T_STRING) {
                if ($iterator->getOffset() + strlen($iterator->getValue()) < $offset) {
                    $beforeParts[] = $iterator->getValue();
                } else {
                    $afterParts[] = $iterator->getValue();
                }
            }

            $iterator->eat();
        }

        // TODO
        $kind = CompletionItemKind::CLASS_;

        /** @var NameContext $nameContext */
        $nameContext = $node->getAttribute('nameContext') ?? new NameContext();

        // TODO use & group use
        if (empty($beforeParts)) {
            if ($startsWithBackslash) {
                $completions->items = yield $this->searchNamespace('\\', $kind, $document);
            } else {
                $completions->items = array_merge(
                    yield $this->searchNamespace($nameContext->namespace, $kind, $document),
                    $this->getAliases($nameContext, $kind)
                );
            }
        } else {
            $prefix = $node->slice(0, count($node->parts) - count($afterParts));
            $completions->items = yield $this->searchNamespace('\\' . (string)$prefix, $kind, $document);
        }

        return $completions;
    }

    /**
     * @resolve CompletionItem[]
     */
    private function searchNamespace(string $namespace, $kind, Document $document): \Generator
    {
        if ($kind === CompletionItemKind::CONSTANT) {
            $category = ReflectionIndexDataProvider::CATEGORY_CONST;
        } elseif ($kind === CompletionItemKind::FUNCTION_) {
            $category = ReflectionIndexDataProvider::CATEGORY_FUNCTION;
        } else {
            $category = ReflectionIndexDataProvider::CATEGORY_CLASS;
        }

        // TODO search case insensitive
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
            } else {
                $names[$entry->key] = false;
            }
        }

        $items = [];
        foreach ($names as $name => $isNamespace) {
            $items[] = $this->makeItem($name, $isNamespace ? CompletionItemKind::MODULE : $kind);
        }

        return $items;
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

        return $item;
    }
}
