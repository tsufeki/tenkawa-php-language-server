<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Refactoring;

use PhpLenientParser\LenientParser;
use PhpLenientParser\LenientParserFactory;
use PhpParser\ErrorHandler;
use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\TextDocumentEdit;
use Tsufeki\Tenkawa\Server\Feature\Common\TextEdit;
use Tsufeki\Tenkawa\Server\Feature\Common\VersionedTextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Feature\Common\WorkspaceEdit;

class RefactoringExecutor
{
    /**
     * @var LenientParser
     */
    private $parser;

    /**
     * @var Lexer
     */
    private $lexer;

    /**
     * @var PrettyPrinterAbstract
     */
    private $printer;

    /**
     * @var Differ
     */
    private $differ;

    public function __construct(Differ $differ)
    {
        $this->lexer = new Lexer\Emulative(['usedAttributes' => [
            'comments',
            'startLine', 'endLine',
            'startFilePos', 'endFilePos',
            'startTokenPos', 'endTokenPos',
        ]]);

        $this->parser = (new LenientParserFactory())->create(LenientParserFactory::ONLY_PHP7, $this->lexer);
        $this->printer = new Standard();
        $this->differ = $differ;
    }

    /**
     * @param callable (Node[] $nodes): \Generator $nodesModifier
     *
     * @resolve WorkspaceEdit
     */
    public function execute(callable $nodesModifier, Document $document): \Generator
    {
        $errorHandler = new ErrorHandler\Collecting();
        $version = $document->getVersion();
        $oldText = $document->getText();
        /** @var Node[] $oldNodes */
        $oldNodes = $this->parser->parse($oldText, $errorHandler) ?? [];
        $oldTokens = $this->lexer->getTokens();

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new CloningVisitor());
        $newNodes = $traverser->traverse($oldNodes);

        yield $nodesModifier($newNodes);

        $newText = $this->printer->printFormatPreserving($newNodes, $oldNodes, $oldTokens);
        $textEdits = yield $this->differ->diff($oldText, $newText);

        return $this->makeWorkspaceEdit($textEdits, $document, $version);
    }

    /**
     * @param TextEdit[] $textEdits
     */
    private function makeWorkspaceEdit(array $textEdits, Document $document, ?int $version): WorkspaceEdit
    {
        $edit = new WorkspaceEdit();
        $edit->changes = [(string)$document->getUri() => $textEdits];
        $textDocumentEdit = new TextDocumentEdit();
        $textDocumentEdit->textDocument = new VersionedTextDocumentIdentifier();
        $textDocumentEdit->textDocument->uri = $document->getUri();
        $textDocumentEdit->textDocument->version = $version;
        $textDocumentEdit->edits = $textEdits;
        $edit->documentChanges = [$textDocumentEdit];

        return $edit;
    }
}
