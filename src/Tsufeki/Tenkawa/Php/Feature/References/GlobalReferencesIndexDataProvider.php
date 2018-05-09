<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\References;

use Tsufeki\Tenkawa\Php\Feature\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolExtractor;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Index\IndexDataProvider;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;
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
    public function getEntries(Document $document, string $origin = null): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        $wholeDocumentRange = new Range(
            PositionUtils::positionFromOffset(0, $document),
            PositionUtils::positionFromOffset(strlen($document->getText()), $document)
        );

        /** @var Symbol[] $symbols */
        $symbols = yield $this->symbolExtractor->getSymbolsInRange($document, $wholeDocumentRange, GlobalSymbol::class);
        $entries = [];

        foreach ($symbols as $symbol) {
            $category = self::CATEGORIES[$symbol->kind] ?? null;
            if ($category !== null) {
                $reference = new Reference();
                $reference->referencedNames = $symbol->referencedNames;
                $reference->uri = $document->getUri();
                $reference->range = $symbol->range;

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
        return 1;
    }
}
