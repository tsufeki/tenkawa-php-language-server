<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Php\Feature\CodeAction\ImportCommandProvider;
use Tsufeki\Tenkawa\Php\Parser\Ast;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Command;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Common\TextDocumentEdit;
use Tsufeki\Tenkawa\Server\Feature\Common\TextEdit;
use Tsufeki\Tenkawa\Server\Feature\Common\VersionedTextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Feature\Common\WorkspaceEdit;
use Tsufeki\Tenkawa\Server\Refactor\EditHelper;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class ImportHelper
{
    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

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
        ReflectionProvider $reflectionProvider,
        Parser $parser,
        EditHelper $editHelper,
        NodeFinder $nodeFinder
    ) {
        $this->reflectionProvider = $reflectionProvider;
        $this->parser = $parser;
        $this->editHelper = $editHelper;
        $this->nodeFinder = $nodeFinder;
    }

    /**
     * @resolve Command[]
     */
    public function getCodeActions(
        string $name,
        string $kind,
        NameContext $nameContext,
        Position $position,
        Document $document,
        int $version = null
    ): \Generator {
        $parts = explode('\\', $name);

        if (($name[0] ?? '') === '\\' || $this->isAlreadyImported($parts, $kind, $nameContext)) {
            return [];
        }

        if (yield $this->isAlreadyResolved($name, $kind, $nameContext, $document)) {
            return [];
        }

        $elements = yield $this->getReflections($name, $kind, $document);
        $commands = [];
        /** @var Element $element */
        foreach ($elements as $element) {
            $importParts = explode('\\', ltrim($element->name, '\\'));
            if (count($parts) > 1) {
                // discard nested parts, import only top-most namespace
                $importParts = array_slice($importParts, 0, -count($parts) + 1);
            }
            $importName = implode('\\', $importParts);
            $command = new Command();
            $command->title = "Import $importName";
            $command->command = ImportCommandProvider::COMMAND;
            $command->arguments = [
                $document->getUri()->getNormalized(),
                $position,
                count($parts) > 1 ? '' : $kind,
                '\\' . $importName,
                $version,
            ];
            $commands[] = $command;
        }

        return $commands;
    }

    /**
     * @param string[] $parts
     */
    public function isAlreadyImported(array $parts, string $kind, NameContext $nameContext): bool
    {
        $importAlias = $parts[0];
        $kind = count($parts) > 1 ? '' : $kind;

        if ($kind === 'const') {
            if (isset($nameContext->constUses[$importAlias])) {
                return true;
            }
        } elseif ($kind === 'function') {
            if (isset($nameContext->functionUses[$importAlias])) {
                return true;
            }
        } else {
            if (isset($nameContext->uses[$importAlias])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @resolve bool
     */
    private function isAlreadyResolved(string $name, string $kind, NameContext $nameContext, Document $document): \Generator
    {
        if ($kind === 'const') {
            foreach ($nameContext->resolveConst($name) as $resolved) {
                if (!empty(yield $this->reflectionProvider->getConst($document, $resolved))) {
                    return true;
                }
            }
        } elseif ($kind === 'function') {
            foreach ($nameContext->resolveFunction($name) as $resolved) {
                if (!empty(yield $this->reflectionProvider->getFunction($document, $resolved))) {
                    return true;
                }
            }
        } else {
            $resolved = $nameContext->resolveClass($name);
            if (!empty(yield $this->reflectionProvider->getClass($document, $resolved))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @resolve Element[]
     */
    private function getReflections(string $name, string $kind, Document $document): \Generator
    {
        if ($kind === 'const') {
            return yield $this->reflectionProvider->getConstsByShortName($document, $name);
        }
        if ($kind === 'function') {
            return yield $this->reflectionProvider->getFunctionsByShortName($document, $name);
        }

        return yield $this->reflectionProvider->getClassesByShortName($document, $name);
    }

    /**
     * @resolve WorkspaceEdit
     */
    public function getImportEdit(
        Document $document,
        Position $position,
        string $kind,
        string $name,
        int $version = null
    ): \Generator {
        /** @var ImportEditData $data */
        $data = yield $this->getImportEditData($document, $position);

        return yield $this->getImportEditWithData($document, $data, $kind, $name, $version);
    }

    /**
     * @resolve WorkspaceEdit
     */
    public function getImportEditWithData(
        Document $document,
        ImportEditData $data,
        string $kind,
        string $name,
        int $version = null
    ): \Generator {
        $lines = [$data->indent . 'use ' . ($kind ? $kind . ' ' : '') . ltrim($name, '\\') . ';'];
        if ($data->appendEmptyLine) {
            $lines[] = '';
        }

        $textEdit = $this->editHelper->insertLines($document, $data->lineNo, $lines);

        return $this->makeWorkspaceEdit($textEdit, $document, $version);
        yield;
    }

    /**
     * @resolve ImportEditData
     */
    public function getImportEditData(
        Document $document,
        Position $position
    ): \Generator {
        $node = yield $this->findNodeForInsert($document, $position);
        $range = $this->rangeFromNodeWithComments($node, $document);
        $indent = $this->editHelper->getIndentForRange($document, $range);

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

    private function makeWorkspaceEdit(TextEdit $textEdit, Document $document, int $version = null): WorkspaceEdit
    {
        $edit = new WorkspaceEdit();
        $edit->changes = [(string)$document->getUri() => [$textEdit]];
        $textDocumentEdit = new TextDocumentEdit();
        $textDocumentEdit->textDocument = new VersionedTextDocumentIdentifier();
        $textDocumentEdit->textDocument->uri = $document->getUri();
        $textDocumentEdit->textDocument->version = $version;
        $textDocumentEdit->edits = [$textEdit];
        $edit->documentChanges = [$textDocumentEdit];

        return $edit;
    }
}
