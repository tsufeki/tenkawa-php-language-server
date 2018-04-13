<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\CodeAction;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Name;
use Tsufeki\Tenkawa\Php\Feature\GlobalsHelper;
use Tsufeki\Tenkawa\Php\Feature\ImportHelper;
use Tsufeki\Tenkawa\Php\Feature\NodeFinder;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\CodeAction\CodeActionContext;
use Tsufeki\Tenkawa\Server\Feature\CodeAction\CodeActionProvider;
use Tsufeki\Tenkawa\Server\Feature\Common\Command;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class ImportGlobalCodeActionProvider implements CodeActionProvider
{
    /**
     * @var ImportHelper
     */
    private $importHelper;

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    public function __construct(ImportHelper $importHelper, NodeFinder $nodeFinder)
    {
        $this->importHelper = $importHelper;
        $this->nodeFinder = $nodeFinder;
    }

    /**
     * @resolve Command[]
     */
    public function getCodeActions(Document $document, Range $range, CodeActionContext $context): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodesIntersectingWithRange($document, $range);

        $version = $document->getVersion();
        $commands = [];
        foreach ($nodes as $node) {
            if ($node instanceof Name) {
                $nodeRange = PositionUtils::rangeFromNodeAttrs($node->getAttributes(), $document);
                /** @var Command $command */
                foreach (yield $this->getCodeActionsAtPosition($nodeRange->start, $document, $version) as $command) {
                    $commands[$command->arguments[2] . '-' . $command->arguments[3]] = $command;
                }
            }
        }

        return array_values($commands);
    }

    /**
     * @resolve Command[]
     */
    private function getCodeActionsAtPosition(Position $position, Document $document, int $version = null): \Generator
    {
        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath($document, $position);
        if (count($nodes) < 2 || !($nodes[0] instanceof Name)) {
            return [];
        }

        $nameContext = $nodes[0]->getAttribute('nameContext') ?? new NameContext();
        $name = $nodes[0]->getAttribute('originalName', $nodes[0]);
        $parentNode = $nodes[1];
        if (!($name instanceof Name) ||
            $name instanceof Name\FullyQualified ||
            $name instanceof Name\Relative
        ) {
            return [];
        }

        $kind = $this->getKind($parentNode);
        if ($kind === null) {
            return [];
        }

        return yield $this->importHelper->getCodeActions(
            (string)$name,
            $kind,
            $nameContext,
            $position,
            $document,
            $version
        );
    }

    /**
     * @param Node|Comment $parentNode
     *
     * @return string|null
     */
    private function getKind($parentNode)
    {
        if (isset(GlobalsHelper::CLASS_REFERENCING_NODES[get_class($parentNode)])) {
            return '';
        }
        if (isset(GlobalsHelper::FUNCTION_REFERENCING_NODES[get_class($parentNode)])) {
            return 'function';
        }
        if (isset(GlobalsHelper::CONST_REFERENCING_NODES[get_class($parentNode)])) {
            return 'const';
        }

        return null;
    }
}
