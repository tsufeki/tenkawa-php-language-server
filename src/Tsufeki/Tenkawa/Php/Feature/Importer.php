<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Php\Parser\Ast;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Common\TextEdit;
use Tsufeki\Tenkawa\Server\Refactor\EditHelper;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class Importer
{
    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var EditHelper
     */
    private $editHelper;

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    public function __construct(
        Parser $parser,
        EditHelper $editHelper,
        NodeFinder $nodeFinder
    ) {
        $this->parser = $parser;
        $this->editHelper = $editHelper;
        $this->nodeFinder = $nodeFinder;
    }

    /**
     * @resolve TextEdit[]|null
     */
    public function getImportEdit(GlobalSymbol $symbol, string $name, $kind): \Generator
    {
        /** @var ImportEditData $data */
        $data = yield $this->getImportEditData($symbol);

        return yield $this->getImportEditWithData($symbol, $data, $name, $kind);
    }

    /**
     * @resolve ImportEditData
     */
    public function getImportEditData(GlobalSymbol $symbol): \Generator
    {
        $node = yield $this->findNodeForInsert($symbol->document, $symbol->range->start);
        $range = $this->rangeFromNodeWithComments($node, $symbol->document);
        $indent = $this->editHelper->getIndentForRange($symbol->document, $range);

        $data = new ImportEditData();
        $data->indent = $indent->render();
        $data->lineNo = $range->start->line;
        $data->appendEmptyLine = true;

        if ($node instanceof Stmt\Use_ || $node instanceof Stmt\GroupUse) {
            $data->lineNo = $range->end->line + 1;
            $data->appendEmptyLine = false;
        }

        return $data;
    }

    /**
     * @resolve TextEdit[]|null
     */
    public function getImportEditWithData(GlobalSymbol $symbol, ImportEditData $data, string $name, $kind): \Generator
    {
        if (!yield $this->canBeImported($symbol, $name, $kind)) {
            return null;
        }

        $modifier = '';
        if ($kind === GlobalSymbol::FUNCTION_) {
            $modifier = 'function ';
        } elseif ($kind === GlobalSymbol::CONST_) {
            $modifier = 'const ';
        }
        $lines = [$data->indent . 'use ' . $modifier . ltrim($name, '\\') . ';'];
        if ($data->appendEmptyLine) {
            $lines[] = '';
        }

        $textEdit = $this->editHelper->insertLines($symbol->document, $data->lineNo, $lines);

        return [$textEdit];
        yield;
    }

    /**
     * @resolve bool
     */
    private function canBeImported(GlobalSymbol $symbol, string $name, $kind): \Generator
    {
        // Is global?
        $parts = explode('\\', ltrim($name, '\\'));
        if (count($parts) === 1) {
            return false;
        }

        // Is in the same namespace?
        $namespaceParts = explode('\\', ltrim($symbol->nameContext->namespace, '\\'));
        if (count($parts) === count($namespaceParts) + 1 &&
            $namespaceParts === array_slice($parts, 0, -1)
        ) {
            return false;
        }

        // Is already imported?
        $lastPart = array_slice($parts, -1)[0];
        if ($kind === GlobalSymbol::FUNCTION_) {
            $uses = $symbol->nameContext->functionUses;
        } elseif ($kind === GlobalSymbol::CONST_) {
            $uses = $symbol->nameContext->constUses;
        } else {
            $uses = $symbol->nameContext->uses;
        }
        if (isset($uses[$lastPart])) {
            return false;
        }

        return true;
        yield;
    }

    /**
     * @resolve Node|null
     */
    private function findNodeForInsert(Document $document, Position $position): \Generator
    {
        /** @var (Node|Comment)[] $nodes */
        $nodes = yield $this->nodeFinder->getNodePath($document, $position);
        /** @var Stmt\Namespace_|null $namespaceNode */
        $namespaceNode = null;
        foreach ($nodes as $node) {
            if ($node instanceof Stmt\Namespace_) {
                $namespaceNode = $node;
                break;
            }
        }

        /** @var Node|null $node */
        $node = null;

        if ($namespaceNode !== null) {
            foreach ($namespaceNode->stmts as $stmt) {
                if ($stmt instanceof Stmt\Use_ || $stmt instanceof Stmt\GroupUse) {
                    $node = $stmt;
                }
            }
            if ($node === null) {
                $node = $this->getFirstStatement($namespaceNode->stmts);
            }
        }

        if ($node === null) {
            /** @var Ast $ast */
            $ast = yield $this->parser->parse($document);
            $node = $this->getFirstStatement($ast->nodes);
        }

        return $node;
    }

    private function rangeFromNodeWithComments(Node $node, Document $document): Range
    {
        $range = PositionUtils::rangeFromNodeAttrs($node->getAttributes(), $document);
        $startOffset = PositionUtils::offsetFromPosition($range->start, $document);

        /** @var Comment $comment */
        foreach ($node->getAttribute('comments') ?? [] as $comment) {
            $offset = $comment->getFilePos();
            if ($offset < $startOffset) {
                $startOffset = $offset;
            }
        }

        return new Range(PositionUtils::positionFromOffset($startOffset, $document), $range->end);
    }

    /**
     * @param Node[] $nodes
     *
     * @return Node|null
     */
    private function getFirstStatement(array $nodes)
    {
        foreach ($nodes as $node) {
            if (!($node instanceof Stmt\InlineHTML)) {
                return $node;
            }
        }
    }
}
