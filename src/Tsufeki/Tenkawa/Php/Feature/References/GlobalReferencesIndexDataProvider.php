<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\References;

use Tsufeki\Tenkawa\Php\Feature\DefinitionSymbol;
use Tsufeki\Tenkawa\Php\Feature\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolExtractor;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Index\IndexDataProvider;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class GlobalReferencesIndexDataProvider implements IndexDataProvider
{
    const CATEGORY_CLASS = 'reference.class';
    const CATEGORY_FUNCTION = 'reference.function';
    const CATEGORY_CONST = 'reference.const';

    const CATEGORIES = [
        GlobalSymbol::CLASS_ => self::CATEGORY_CLASS,
        GlobalSymbol::FUNCTION_ => self::CATEGORY_FUNCTION,
        GlobalSymbol::CONST_ => self::CATEGORY_CONST,
    ];

    /**
     * @var SymbolExtractor
     */
    private $symbolExtractor;

    public function __construct(SymbolExtractor $symbolExtractor)
    {
        $this->symbolExtractor = $symbolExtractor;
    }

    /**
     * @resolve IndexEntry[]
     */
    public function getEntries(Document $document): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        $wholeDocumentRange = new Range(
            PositionUtils::positionFromOffset(0, $document),
            PositionUtils::positionFromOffset(strlen($document->getText()), $document)
        );

        $entries = [];

        /** @var Symbol[] $symbols */
        $symbols = yield $this->symbolExtractor->getSymbolsInRange($document, $wholeDocumentRange, GlobalSymbol::class);
        $entries = $this->makeEntries($symbols, $document->getUri());

        $symbols = yield $this->symbolExtractor->getSymbolsInRange($document, $wholeDocumentRange, DefinitionSymbol::class);
        $entries = array_merge($entries, $this->makeEntries($symbols, $document->getUri()));

        return $entries;
    }

    /**
     * @param Symbol[] $symbols
     *
     * @return IndexEntry[]
     */
    private function makeEntries(array $symbols, Uri $uri): array
    {
        $entries = [];

        foreach ($symbols as $symbol) {
            if ($symbol->kind === GlobalSymbol::CONST_) {
                $lowercaseName = strtolower($symbol->referencedNames[1] ?? $symbol->referencedNames[0]);
                if (in_array($lowercaseName, ['\\null', '\\true', '\\false'], true)) {
                    continue;
                }
            }

            $category = self::CATEGORIES[$symbol->kind] ?? null;
            if ($category !== null) {
                $reference = new Reference();
                $reference->referencedNames = $symbol->referencedNames;
                $reference->uri = $uri;
                $reference->range = $symbol->range;
                $reference->isDefinition = $symbol instanceof DefinitionSymbol;

                foreach ($reference->referencedNames as $referencedName) {
                    $entry = new IndexEntry();
                    $entry->sourceUri = $reference->uri;
                    $entry->category = $category;
                    $entry->key = $referencedName;
                    $entry->data = $reference;

                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    public function getVersion(): int
    {
        return 4;
    }
}
