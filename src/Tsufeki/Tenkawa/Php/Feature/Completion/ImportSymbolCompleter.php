<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use Tsufeki\Tenkawa\Php\Feature\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Feature\Importer;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\TextEdit;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItem;
use Tsufeki\Tenkawa\Server\Index\Index;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class ImportSymbolCompleter implements SymbolCompleter
{
    /**
     * @var Index
     */
    private $index;

    /**
     * @var Importer
     */
    private $importer;

    public function __construct(Index $index, Importer $importer)
    {
        $this->index = $index;
        $this->importer = $importer;
    }

    public function getTriggerCharacters(): array
    {
        return [];
    }

    /**
     * @resolve CompletionItem[]
     */
    public function getCompletions(Symbol $symbol, Position $position): \Generator
    {
        if (!($symbol instanceof GlobalSymbol) ||
            strpos($symbol->originalName, '\\') !== false ||
            $symbol->isImport ||
            $symbol->kind === GlobalSymbol::NAMESPACE_
        ) {
            return [];
        }

        /** @var CompletionItem[] $items */
        $items = [];
        $importData = yield $this->importer->getImportEditData($symbol);
        foreach (GlobalSymbolCompleter::SEARCH_KINDS as $kind) {
            $names = yield $this->query($kind, $symbol->document);

            foreach ($names as $name) {
                $textEdits = yield $this->importer->getImportEditWithData($symbol, $importData, $name, $kind);
                if ($textEdits !== null) {
                    $items[] = $this->makeItem($name, $kind, $textEdits, $symbol->kind !== GlobalSymbol::FUNCTION_);
                }
            }
        }

        return $items;
    }

    /**
     * @resolve string[]
     */
    private function query(
        string $kind,
        Document $document
    ): \Generator {
        $query = new Query();
        $query->category = GlobalSymbolCompleter::INDEX_CATEGORIES[$kind];
        $query->key = '';
        $query->match = Query::PREFIX;
        $query->includeData = false;

        /** @var IndexEntry[] $entries */
        $entries = yield $this->index->search($document, $query);
        $names = [];
        foreach ($entries as $entry) {
            $names[] = $entry->key;
        }

        return $names;
    }

    /**
     * @param TextEdit[] $textEdits
     */
    private function makeItem(string $name, $kind, array $textEdits, bool $addTrailingParen): CompletionItem
    {
        $shortName = StringUtils::getShortName($name);

        $item = new CompletionItem();
        $item->label = $shortName;
        $item->kind = GlobalSymbolCompleter::COMPLETION_KINDS[$kind];
        $item->detail = "$name\n\n+ auto-import";
        $item->insertText = $shortName;

        if ($kind === GlobalSymbol::FUNCTION_ && $addTrailingParen) {
            $item->insertText .= '(';
        }

        $item->additionalTextEdits = $textEdits;

        return $item;
    }
}
