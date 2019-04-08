<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Refactoring;

use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\NameHelper;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Php\Symbol\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Symbol\SymbolExtractor;
use Tsufeki\Tenkawa\Php\Symbol\SymbolReflection;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\CodeAction\CodeActionContext;
use Tsufeki\Tenkawa\Server\Feature\CodeAction\CodeActionProvider;
use Tsufeki\Tenkawa\Server\Feature\Common\Command;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Common\TextDocumentEdit;
use Tsufeki\Tenkawa\Server\Feature\Common\TextEdit;
use Tsufeki\Tenkawa\Server\Feature\Common\VersionedTextDocumentIdentifier;
use Tsufeki\Tenkawa\Server\Feature\Common\WorkspaceEdit;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class ImportCodeActionProvider implements CodeActionProvider
{
    /**
     * @var Importer
     */
    private $importer;

    /**
     * @var SymbolExtractor
     */
    private $symbolExtractor;

    /**
     * @var SymbolReflection
     */
    private $symbolReflection;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    public function __construct(
        Importer $importer,
        SymbolExtractor $symbolExtractor,
        SymbolReflection $symbolReflection,
        ReflectionProvider $reflectionProvider
    ) {
        $this->importer = $importer;
        $this->symbolExtractor = $symbolExtractor;
        $this->symbolReflection = $symbolReflection;
        $this->reflectionProvider = $reflectionProvider;
    }

    /**
     * @resolve Command[]
     */
    public function getCodeActions(Document $document, Range $range, CodeActionContext $context): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        $version = $document->getVersion();
        $commands = [];

        /** @var GlobalSymbol[] $symbols */
        $symbols = yield $this->symbolExtractor->getSymbolsInRange($document, $range, GlobalSymbol::class);
        foreach ($symbols as $symbol) {
            $commands = array_merge($commands, yield $this->getCodeActionsForSymbol($symbol, $version));
        }

        return array_values(array_unique($commands, SORT_REGULAR));
    }

    /**
     * @resolve Command[]
     */
    private function getCodeActionsForSymbol(GlobalSymbol $symbol, ?int $version): \Generator
    {
        if ($symbol->kind === GlobalSymbol::NAMESPACE_ ||
            $symbol->isImport ||
            (trim($symbol->originalName)[0] ?? '\\') === '\\' ||
            !empty(yield $this->symbolReflection->getReflectionFromSymbol($symbol))
        ) {
            return [];
        }

        $importData = yield $this->importer->getImportEditData($symbol);
        $name = StringUtils::replace('~\\s~', '', $symbol->originalName);
        $parts = explode('\\', $name);
        $commands = [];

        /** @var Element $element */
        foreach (yield $this->getReflections($name, $symbol->kind, $symbol->document) as $element) {
            if (NameHelper::isSpecial($element->name)) {
                continue;
            }

            $kind = $symbol->kind;
            $importParts = explode('\\', ltrim($element->name, '\\'));
            if (count($parts) > 1) {
                // discard nested parts, import only top-most namespace
                $importParts = array_slice($importParts, 0, -count($parts) + 1);
                $kind = GlobalSymbol::NAMESPACE_;
            }
            $importName = implode('\\', $importParts);

            $textEdits = yield $this->importer->getImportEditWithData(
                $symbol,
                $importData,
                '\\' . $importName,
                $kind
            );

            if ($textEdits !== null) {
                $command = new Command();
                $command->title = "Import $importName";
                $command->command = WorkspaceEditCommandProvider::COMMAND;
                $command->arguments = [
                    $command->title,
                    $this->makeWorkspaceEdit($textEdits, $symbol->document, $version),
                ];
                $commands[] = $command;
            }
        }

        return $commands;
    }

    /**
     * @resolve Element[]
     */
    private function getReflections(string $name, string $kind, Document $document): \Generator
    {
        if ($kind === GlobalSymbol::CONST_) {
            return yield $this->reflectionProvider->getConstsByShortName($document, $name);
        }

        if ($kind === GlobalSymbol::FUNCTION_) {
            return yield $this->reflectionProvider->getFunctionsByShortName($document, $name);
        }

        return yield $this->reflectionProvider->getClassesByShortName($document, $name);
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
