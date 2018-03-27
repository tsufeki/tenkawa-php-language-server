<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Language;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Php\Parser\Ast;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Php\Parser\TokenIterator;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionIndexDataProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Index\Index;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;
use Tsufeki\Tenkawa\Server\Language\CompletionProvider;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionContext;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionItem;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionItemKind;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionList;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class GlobalsCompletionProvider implements CompletionProvider
{
    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    /**
     * @var Index
     */
    private $index;

    public function __construct(Parser $parser, Index $index, NodeFinder $nodeFinder)
    {
        $this->parser = $parser;
        $this->index = $index;
        $this->nodeFinder = $nodeFinder;
    }

    public function getTriggerCharacters(): array
    {
        return ['\\'];
    }

    public function getCompletions(
        Document $document,
        Position $position,
        CompletionContext $context = null
    ): \Generator {
        $completions = new CompletionList();

        if ($document->getLanguage() !== 'php') {
            return $completions;
        }

        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath($document, $position);

        if (count($nodes) < 2 || !($nodes[0] instanceof Name)) {
            return $completions;
        }

        $name = $nodes[0];
        $node = $nodes[1];
        $parentNode = $nodes[2] ?? null;
        assert($node instanceof Node);
        assert($parentNode === null || $parentNode instanceof Node);

        // TODO: namespaced vs global resolve of unqualified names
        list($beforeParts, $afterParts) = yield $this->splitName($name, $position, $document);
        $kinds = $this->getKinds($node, $parentNode);
        $absolute = $this->isAbsolute($name, $node);

        if ($node instanceof Stmt\UseUse && $parentNode instanceof Stmt\GroupUse) {
            $name = Name::concat($parentNode->prefix, $name);
        }
        assert($name !== null);

        /** @var CompletionItem[][] $items */
        $items = [];
        foreach ($kinds as $kind) {
            if (empty($beforeParts)) {
                if ($absolute) {
                    $items[] = yield $this->searchNamespace('\\', $kind, $document);
                } else {
                    /** @var NameContext $nameContext */
                    $nameContext = $name->getAttribute('nameContext') ?? new NameContext();
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
                $prefix = $name->slice(0, count($name->parts) - count($afterParts));
                $items[] = yield $this->searchNamespace('\\' . (string)$prefix, $kind, $document);
            }
        }

        $completions->items = array_unique(array_merge(...$items), SORT_REGULAR);

        return $completions;
    }

    /**
     * @resolve string[][] Two element array: name parts before the position
     *                     and after (includes part the position is on).
     */
    private function splitName(Name $name, Position $position, Document $document): \Generator
    {
        /** @var Ast $ast */
        $ast = yield $this->parser->parse($document);
        $offset = PositionUtils::offsetFromPosition($position, $document);

        $iterator = TokenIterator::fromNode($name, $ast->tokens);
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

        return [$beforeParts, $afterParts];
    }

    /**
     * @return int[] CompletionItemKind[]
     */
    private function getKinds(Node $node, Node $parentNode = null): array
    {
        if (isset(GlobalsHelper::FUNCTION_REFERENCING_NODES[get_class($node)])) {
            return [CompletionItemKind::FUNCTION_];
        }
        if (isset(GlobalsHelper::CONST_REFERENCING_NODES[get_class($node)])) {
            // const fetch may be an incomplete function call or static class member access
            return [
                CompletionItemKind::CONSTANT,
                CompletionItemKind::FUNCTION_,
                CompletionItemKind::CLASS_,
            ];
        }
        if (isset(GlobalsHelper::NAMESPACE_REFERENCING_NODES[get_class($node)])) {
            return [CompletionItemKind::MODULE];
        }
        if ($node instanceof Stmt\UseUse) {
            assert($parentNode instanceof Stmt\Use_ || $parentNode instanceof Stmt\GroupUse);

            return [$this->getKindFromUse($node) ?? $this->getKindFromUse($parentNode) ?? CompletionItemKind::CLASS_];
        }
        if ($node instanceof Stmt\GroupUse) {
            return [$this->getKindFromUse($node) ?? CompletionItemKind::CLASS_];
        }

        return [CompletionItemKind::CLASS_];
    }

    /**
     * @param Stmt\Use_|Stmt\GroupUse|Stmt\UseUse $useNode
     *
     * @return int|null CompletionItemKind
     */
    private function getKindFromUse(Node $useNode)
    {
        if ($useNode->type === Stmt\Use_::TYPE_FUNCTION) {
            return CompletionItemKind::FUNCTION_;
        }
        if ($useNode->type === Stmt\Use_::TYPE_CONSTANT) {
            return CompletionItemKind::CONSTANT;
        }
        if ($useNode->type === Stmt\Use_::TYPE_NORMAL) {
            return CompletionItemKind::CLASS_;
        }

        return null;
    }

    private function isAbsolute(Name $name, Node $node): bool
    {
        return $name->getAttribute('originalName', $name) instanceof Name\FullyQualified
            || $node instanceof Stmt\UseUse
            || $node instanceof Stmt\GroupUse
            || $node instanceof Stmt\Namespace_;
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
