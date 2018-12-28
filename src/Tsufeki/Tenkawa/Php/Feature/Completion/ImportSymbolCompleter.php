<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use Tsufeki\Tenkawa\Php\Feature\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Feature\Refactoring\Importer;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\NameHelper;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\TextEdit;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionItem;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionList;
use Tsufeki\Tenkawa\Server\Feature\Configuration\ConfigurationFeature;
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

    /**
     * @var ConfigurationFeature
     */
    private $configurationFeature;

    /**
     * @var string[]
     */
    private $defaultExtensions;

    /**
     * @param string[] $defaultExtensions
     */
    public function __construct(array $defaultExtensions, Index $index, Importer $importer, ConfigurationFeature $configurationFeature)
    {
        $this->defaultExtensions = $defaultExtensions;
        $this->index = $index;
        $this->importer = $importer;
        $this->configurationFeature = $configurationFeature;
    }

    public function getTriggerCharacters(): array
    {
        return [];
    }

    /**
     * @resolve CompletionList
     */
    public function getCompletions(Symbol $symbol, Position $position): \Generator
    {
        $completions = new CompletionList();
        if (!($symbol instanceof GlobalSymbol) ||
            strpos($symbol->originalName, '\\') !== false ||
            $symbol->isImport ||
            $symbol->kind === GlobalSymbol::NAMESPACE_ ||
            (yield $this->configurationFeature->get('completion.autoImport', $symbol->document)) === false
        ) {
            return $completions;
        }

        $completions->isIncomplete = true;
        $importData = yield $this->importer->getImportEditData($symbol);
        foreach (GlobalSymbolCompleter::SEARCH_KINDS as $kind) {
            $names = yield $this->query($kind, $symbol->originalName, $symbol->document);

            foreach ($names as $name) {
                $textEdits = yield $this->importer->getImportEditWithData($symbol, $importData, $name, $kind);
                if ($textEdits !== null) {
                    $completions->items[] = $this->makeItem($name, $kind, $textEdits, $symbol->kind !== GlobalSymbol::FUNCTION_);
                }
            }
        }

        return $completions;
    }

    /**
     * @resolve string[]
     */
    private function query(
        string $kind,
        string $fuzzyQuery,
        Document $document
    ): \Generator {
        $query = new Query();
        $query->category = GlobalSymbolCompleter::INDEX_CATEGORIES[$kind];
        $query->key = '';
        $query->match = Query::SUFFIX;
        $query->fuzzy = $fuzzyQuery;
        $query->fuzzySeparator = '\\';
        $query->limit = GlobalSymbolCompleter::ITEM_LIMIT;
        $query->includeData = false;
        $query->tag = yield $this->getTags($document);

        /** @var IndexEntry[] $entries */
        $entries = yield $this->index->search($document, $query);
        $names = [];
        foreach ($entries as $entry) {
            if (NameHelper::isSpecial($entry->key)) {
                continue;
            }

            $names[] = $entry->key;
        }

        return $names;
    }

    private function getTags(Document $document): \Generator
    {
        $extensions = (yield $this->configurationFeature->get('completion.extensions', $document)) ?? [];
        $extensions = array_values(array_unique(array_merge($this->defaultExtensions, $extensions)));
        $extensions = array_map(function (string $ext) { return strtolower("ext:$ext"); }, $extensions);
        $extensions[] = null;

        return $extensions;
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
        $item->detail = "use $name\n\n(auto-import)";
        $item->insertText = $shortName;

        if ($kind === GlobalSymbol::FUNCTION_ && $addTrailingParen) {
            $item->insertText .= '(';
        }

        $item->additionalTextEdits = $textEdits;

        return $item;
    }
}
