<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\References;

use Tsufeki\Tenkawa\Php\Feature\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolReflection;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Server\Index\Index;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;
use Tsufeki\Tenkawa\Server\Index\Query;

class GlobalReferenceFinder implements ReferenceFinder
{
    /**
     * @var Index
     */
    private $index;

    /**
     * @var SymbolReflection
     */
    private $symbolReflection;

    public function __construct(Index $index, SymbolReflection $symbolReflection)
    {
        $this->index = $index;
        $this->symbolReflection = $symbolReflection;
    }

    /**
     * @resolve Reference[]
     */
    public function getReferences(Symbol $symbol, bool $includeDeclaration = false): \Generator
    {
        if (!in_array($symbol->kind, GlobalSymbol::KINDS, true)) {
            return [];
        }

        /** @var Element[] $elements */
        $elements = yield $this->symbolReflection->getReflectionFromSymbol($symbol);
        $category = GlobalReferencesIndexDataProvider::CATEGORIES[$symbol->kind] ?? null;
        if ($category === null || empty($elements)) {
            return [];
        }

        $name = $elements[0]->name;
        $query = new Query();
        $query->category = $category;
        $query->key = $name;
        $query->match = Query::FULL;

        /** @var array<string,bool> $altNameExistsCache */
        $altNameExistsCache = [];

        $references = [];
        /** @var IndexEntry $entry */
        foreach (yield $this->index->search($symbol->document, $query) as $entry) {
            /** @var Reference $reference */
            $reference = $entry->data;
            if (!$includeDeclaration && $reference->isDefinition) {
                continue;
            }

            foreach ($reference->referencedNames as $referencedName) {
                if ($name === $referencedName) {
                    $references[] = $reference;
                    break;
                }

                if (!isset($altNameExistsCache[$referencedName])) {
                    $altSymbol = new GlobalSymbol();
                    $altSymbol->kind = $symbol->kind;
                    $altSymbol->referencedNames = [$referencedName];
                    $altSymbol->document = $symbol->document;

                    $altElements = yield $this->symbolReflection->getReflectionFromSymbol($altSymbol);
                    $altNameExistsCache[$referencedName] = !empty($altElements);
                }

                if ($altNameExistsCache[$referencedName]) {
                    break;
                }
            }
        }

        return $references;
    }
}
