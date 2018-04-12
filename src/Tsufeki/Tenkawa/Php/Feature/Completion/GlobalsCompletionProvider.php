<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Php\Feature\GlobalsHelper;
use Tsufeki\Tenkawa\Php\Feature\NodeFinder;
use Tsufeki\Tenkawa\Php\Parser\Ast;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Php\Parser\TokenIterator;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionContext;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItemKind;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionList;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionProvider;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

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
     * @var GlobalsCompletionHelper
     */
    private $globalCompletionHelper;

    public function __construct(
        Parser $parser,
        NodeFinder $nodeFinder,
        GlobalsCompletionHelper $globalCompletionHelper
    ) {
        $this->parser = $parser;
        $this->nodeFinder = $nodeFinder;
        $this->globalCompletionHelper = $globalCompletionHelper;
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
        if ($document->getLanguage() !== 'php') {
            return new CompletionList();
        }

        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath($document, $position);

        if (count($nodes) < 2 || !($nodes[0] instanceof Name)) {
            return new CompletionList();
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
            $beforeParts = array_merge($parentNode->prefix->parts, $beforeParts);
        }

        return yield $this->globalCompletionHelper->getCompletions(
            $document,
            $beforeParts,
            $afterParts,
            $kinds,
            $absolute,
            $name->getAttribute('nameContext') ?? new NameContext()
        );
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
}
