<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Refactoring;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use Tsufeki\Tenkawa\Php\Composer\ComposerService;
use Tsufeki\Tenkawa\Php\Parser\Ast;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Php\Refactoring\RefactoringExecutor;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Feature\CodeAction\CodeActionContext;
use Tsufeki\Tenkawa\Server\Feature\CodeAction\CodeActionProvider;
use Tsufeki\Tenkawa\Server\Feature\Command\CommandProvider;
use Tsufeki\Tenkawa\Server\Feature\Common\Command;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Common\WorkspaceEdit;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\Diagnostic;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\DiagnosticSeverity;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\DiagnosticsProvider;
use Tsufeki\Tenkawa\Server\Feature\WorkspaceEdit\WorkspaceEditFeature;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class FixAutoloadClassNameRefactoring implements DiagnosticsProvider, CodeActionProvider, CommandProvider
{
    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var ComposerService
     */
    private $composerService;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var RefactoringExecutor
     */
    private $refactoringExecutor;

    /**
     * @var WorkspaceEditFeature
     */
    private $workspaceEditFeature;

    public function __construct(
        Parser $parser,
        ComposerService $composerService,
        DocumentStore $documentStore,
        RefactoringExecutor $refactoringExecutor,
        WorkspaceEditFeature $workspaceEditFeature
    ) {
        $this->parser = $parser;
        $this->composerService = $composerService;
        $this->documentStore = $documentStore;
        $this->refactoringExecutor = $refactoringExecutor;
        $this->workspaceEditFeature = $workspaceEditFeature;
    }

    /**
     * @resolve Diagnostic[]
     */
    public function getDiagnostics(Document $document): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        /** @var Ast */
        $ast = yield $this->parser->parse($document);
        [$nsNode, $classNode] = $this->findNamespaceAndClassNodes($ast->nodes);
        $autoloadFullClass = yield $this->composerService->getAutoloadClassForFile($document);
        if ($nsNode === null || $classNode === null || $autoloadFullClass === null) {
            return [];
        }

        assert($nsNode instanceof Node\Name && $classNode instanceof Node\Identifier);
        $autoloadClass = StringUtils::getShortName($autoloadFullClass);
        $autoloadNamespace = trim(StringUtils::getNamespace($autoloadFullClass), '\\');
        $diags = [];

        if ($autoloadNamespace !== (string)$nsNode) {
            $diag = new Diagnostic();
            $diag->message = 'Namespace is not suitable for autoloading because of wrong name';
            $diag->range = PositionUtils::rangeFromNodeAttrs($nsNode->getAttributes(), $document);
            $diag->severity = DiagnosticSeverity::WARNING;
            $diag->source = 'php';
            $diags[] = $diag;
        }

        if ($autoloadClass !== (string)$classNode) {
            $diag = new Diagnostic();
            $diag->message = "Class can't be autoloaded because of wrong name";
            $diag->range = PositionUtils::rangeFromNodeAttrs($classNode->getAttributes(), $document);
            $diag->severity = DiagnosticSeverity::WARNING;
            $diag->source = 'php';
            $diags[] = $diag;
        }

        return $diags;
    }

    /**
     * @resolve Command[]
     */
    public function getCodeActions(Document $document, Range $range, CodeActionContext $context): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        /** @var Ast */
        $ast = yield $this->parser->parse($document);
        [$nsNode, $classNode] = $this->findNamespaceAndClassNodes($ast->nodes);
        $autoloadFullClass = yield $this->composerService->getAutoloadClassForFile($document);
        if ($nsNode === null || $classNode === null || $autoloadFullClass === null) {
            return [];
        }

        assert($nsNode instanceof Node\Name && $classNode instanceof Node\Identifier);
        $autoloadClass = StringUtils::getShortName($autoloadFullClass);
        $autoloadNamespace = trim(StringUtils::getNamespace($autoloadFullClass), '\\');
        $codeActions = [];

        $nsRange = PositionUtils::rangeFromNodeAttrs($nsNode->getAttributes(), $document);
        if ($autoloadNamespace !== (string)$nsNode && PositionUtils::overlapZeroLength($range, $nsRange)) {
            $cmd = new Command();
            $cmd->title = "Change name to $autoloadNamespace";
            $cmd->command = $this->getCommand();
            $cmd->arguments = ['namespace', $document->getUri(), $document->getVersion()];
            $codeActions[] = $cmd;
        }

        $classRange = PositionUtils::rangeFromNodeAttrs($classNode->getAttributes(), $document);
        if ($autoloadClass !== (string)$classNode && PositionUtils::overlapZeroLength($range, $classRange)) {
            $cmd = new Command();
            $cmd->title = "Change name to $autoloadClass";
            $cmd->command = $this->getCommand();
            $cmd->arguments = ['class', $document->getUri(), $document->getVersion()];
            $codeActions[] = $cmd;
        }

        return $codeActions;
    }

    public function getCommand(): string
    {
        return 'tenkawaphp.refactoring.fixAutoloadClassName';
    }

    public function execute(string $what, Uri $uri, ?int $version): \Generator
    {
        $document = $this->documentStore->get($uri);
        if ($document->getLanguage() !== 'php' || $document->getVersion() !== $version) {
            return;
        }

        /** @var WorkspaceEdit $edit */
        $edit = yield $this->refactoringExecutor->execute(function (array $nodes) use ($what, $document): \Generator {
            [$nsNode, $classNode] = $this->findNamespaceAndClassNodes($nodes);
            $autoloadFullClass = yield $this->composerService->getAutoloadClassForFile($document);
            if ($nsNode === null || $classNode === null || $autoloadFullClass === null) {
                return;
            }

            assert($nsNode instanceof Node\Name && $classNode instanceof Node\Identifier);
            if ($what === 'namespace') {
                $autoloadNamespace = trim(StringUtils::getNamespace($autoloadFullClass), '\\');
                $nsNode->parts = (new Name($autoloadNamespace))->parts;
            } else {
                $autoloadClass = StringUtils::getShortName($autoloadFullClass);
                $classNode->name = $autoloadClass;
            }

            return;
            yield;
        }, $document);

        yield $this->workspaceEditFeature->applyWorkspaceEdit('Change name', $edit);
    }

    /**
     * @param Node[] $nodes
     *
     * @return (Node|null)[]
     */
    private function findNamespaceAndClassNodes(array $nodes): array
    {
        /** @var Stmt\Namespace_|null $nsNode */
        $nsNode = null;
        foreach ($nodes as $node) {
            if ($node instanceof Stmt\Namespace_ && $nsNode === null) {
                $nsNode = $node;
            } elseif (!($node instanceof Stmt\Declare_ || $node instanceof Stmt\Nop)) {
                return [null, null];
            }
        }

        if ($nsNode === null || $nsNode->name === null) {
            return [null, null];
        }

        /** @var Stmt\ClassLike|null $classNode */
        $classNode = null;
        foreach ($nsNode->stmts as $node) {
            if ($node instanceof Stmt\ClassLike && $classNode === null) {
                $classNode = $node;
            }
        }

        if ($classNode === null || $classNode->name === null) {
            return [null, null];
        }

        return [$nsNode->name, $classNode->name];
    }
}
