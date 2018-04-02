<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Language;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Language\CommandProvider;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Common\Range;
use Tsufeki\Tenkawa\Server\Protocol\Common\TextDocumentEdit;
use Tsufeki\Tenkawa\Server\Protocol\Common\TextEdit;
use Tsufeki\Tenkawa\Server\Protocol\Common\VersionedTextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Protocol\Common\WorkspaceEdit;
use Tsufeki\Tenkawa\Server\Protocol\LanguageClient;
use Tsufeki\Tenkawa\Server\Refactor\EditHelper;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class ImportCommandProvider implements CommandProvider
{
    const COMMAND = 'tenkawa.php.refactor.import';

    /**
     * @var NodeFinder
     */
    private $nodeFinder;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var EditHelper
     */
    private $editHelper;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var LanguageClient
     */
    private $client;

    public function __construct(
        NodeFinder $nodeFinder,
        Parser $parser,
        EditHelper $editHelper,
        DocumentStore $documentStore,
        LanguageClient $client
    ) {
        $this->nodeFinder = $nodeFinder;
        $this->parser = $parser;
        $this->editHelper = $editHelper;
        $this->documentStore = $documentStore;
        $this->client = $client;
    }

    public function getCommand(): string
    {
        return self::COMMAND;
    }

    public function execute(Uri $uri, Position $position, string $kind, string $name, int $version = null): \Generator
    {
        $document = $this->documentStore->get($uri);

        $node = yield $this->findNodeForInsert($document, $position);
        $range = $this->rangeFromNodeWithComments($node, $document);
        $indent = $this->editHelper->getIndentForRange($document, $range);

        $useLine = $indent->render() . 'use ' . ($kind ? $kind . ' ' : '') . ltrim($name, '\\') . ';';
        $lineNo = $range->start->line;
        $lines = [$useLine, ''];

        if ($node instanceof Stmt\Use_ || $node instanceof Stmt\GroupUse) {
            $lineNo = $range->end->line + 1;
            $lines = [$useLine];
        }

        $textEdit = $this->editHelper->insertLines($document, $lineNo, $lines);

        yield $this->applyEdit($textEdit, $document, $version);
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

    private function applyEdit(TextEdit $textEdit, Document $document, int $version = null): \Generator
    {
        $edit = new WorkspaceEdit();
        $edit->changes = [(string)$document->getUri() => [$textEdit]];
        $textDocumentEdit = new TextDocumentEdit();
        $textDocumentEdit->textDocument = new VersionedTextDocumentIdentifier();
        $textDocumentEdit->textDocument->uri = $document->getUri();
        $textDocumentEdit->textDocument->version = $version;
        $textDocumentEdit->edits = [$textEdit];
        $edit->documentChanges = [$textDocumentEdit];

        yield $this->client->applyWorkspaceEdit('Import', $edit);
    }
}
