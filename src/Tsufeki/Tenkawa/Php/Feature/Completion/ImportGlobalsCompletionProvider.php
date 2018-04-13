<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Php\Feature\GlobalsHelper;
use Tsufeki\Tenkawa\Php\Feature\NodeFinder;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionContext;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItemKind;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionList;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionProvider;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class ImportGlobalsCompletionProvider implements CompletionProvider
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
        return [];
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

        $nameContext = $nodes[0]->getAttribute('nameContext') ?? new NameContext();
        $name = $nodes[0]->getAttribute('originalName', $nodes[0]);
        $node = $nodes[1];
        assert($node instanceof Node);

        if (count($name->parts) !== 1 ||
            $name instanceof Name\FullyQualified ||
            $node instanceof Stmt\UseUse ||
            $node instanceof Stmt\Namespace_ ||
            $this->endsWithBackslash($name, $document)
        ) {
            return new CompletionList();
        }

        return yield $this->globalCompletionHelper->getImportCompletions(
            $document,
            $position,
            $this->getKinds($node),
            $nameContext
        );
    }

    private function endsWithBackslash(Name $name, Document $document): bool
    {
        $range = PositionUtils::rangeFromNodeAttrs($name->getAttributes(), $document);
        $endOffset = max(PositionUtils::offsetFromPosition($range->end, $document) - 1, 0);

        return ($document->getText()[$endOffset] ?? '') === '\\';
    }

    /**
     * @return int[] CompletionItemKind[]
     */
    private function getKinds(Node $node): array
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
        if (isset(GlobalsHelper::CLASS_REFERENCING_NODES[get_class($node)])) {
            return [CompletionItemKind::CLASS_];
        }

        return [];
    }
}
