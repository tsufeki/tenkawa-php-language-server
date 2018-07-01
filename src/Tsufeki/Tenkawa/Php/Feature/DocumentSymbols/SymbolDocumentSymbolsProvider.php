<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\DocumentSymbols;

use Tsufeki\Tenkawa\Php\Feature\DefinitionSymbol;
use Tsufeki\Tenkawa\Php\Feature\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Feature\MemberSymbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolExtractor;
use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Location;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Common\SymbolInformation;
use Tsufeki\Tenkawa\Server\Feature\Common\SymbolKind;
use Tsufeki\Tenkawa\Server\Feature\DocumentSymbols\DocumentSymbolsProvider;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class SymbolDocumentSymbolsProvider implements DocumentSymbolsProvider
{
    /**
     * @var SymbolExtractor
     */
    private $symbolExtractor;

    const KINDS = [
        GlobalSymbol::CLASS_ => SymbolKind::CLASS_,
        GlobalSymbol::FUNCTION_ => SymbolKind::FUNCTION_,
        GlobalSymbol::CONST_ => SymbolKind::CONSTANT,
        MemberSymbol::PROPERTY => SymbolKind::PROPERTY,
        MemberSymbol::CLASS_CONST => SymbolKind::CONSTANT,
        MemberSymbol::METHOD => SymbolKind::METHOD,
    ];

    const KIND_ORDER = [
        GlobalSymbol::CONST_ => 1,
        GlobalSymbol::FUNCTION_ => 2,
        GlobalSymbol::CLASS_ => 3,
        MemberSymbol::CLASS_CONST => 1,
        MemberSymbol::PROPERTY => 2,
        MemberSymbol::METHOD => 3,
    ];

    public function __construct(SymbolExtractor $symbolExtractor)
    {
        $this->symbolExtractor = $symbolExtractor;
    }

    public function getSymbols(Document $document): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        $wholeDocumentRange = new Range(
            PositionUtils::positionFromOffset(0, $document),
            PositionUtils::positionFromOffset(strlen($document->getText()), $document)
        );

        /** @var DefinitionSymbol[] $symbols */
        $symbols = yield $this->symbolExtractor->getSymbolsInRange($document, $wholeDocumentRange, DefinitionSymbol::class);
        /** @var SymbolInformation[] $symbolInfos */
        $symbolInfos = [];

        $this->sort($symbols);

        foreach ($symbols as $symbol) {
            $symbolInfo = new SymbolInformation();
            $symbolInfo->name = $symbol->referencedNames[0];

            switch ($symbol->kind) {
                case GlobalSymbol::CLASS_:
                case GlobalSymbol::FUNCTION_:
                case GlobalSymbol::CONST_:
                    $symbolInfo->containerName = ltrim($symbol->nameContext->namespace, '\\');
                    break;
                case MemberSymbol::PROPERTY:
                case MemberSymbol::CLASS_CONST:
                case MemberSymbol::METHOD:
                    $symbolInfo->containerName = ltrim($symbol->nameContext->class ?? '', '\\');
                    break;
                default:
                    continue 2;
            }

            $symbolInfo->kind = self::KINDS[$symbol->kind];
            if ($this->isConstructor($symbol)) {
                $symbolInfo->kind = SymbolKind::CONSTRUCTOR;
            }
            if ($symbol->kind === MemberSymbol::PROPERTY) {
                $symbolInfo->name = '$' . $symbolInfo->name;
            }

            $symbolInfo->location = new Location();
            $symbolInfo->location->uri = $document->getUri();
            $symbolInfo->location->range = $symbol->definitionRange;
            $symbolInfos[] = $symbolInfo;
        }

        return $symbolInfos;
    }

    private function isConstructor(DefinitionSymbol $symbol): bool
    {
        return $symbol->kind === MemberSymbol::METHOD && in_array(strtolower($symbol->referencedNames[0]), ['__construct', '__destruct']);
    }

    /**
     * @param DefinitionSymbol[] $symbols
     */
    private function sort(array &$symbols)
    {
        usort($symbols, function (DefinitionSymbol $a, DefinitionSymbol $b) {
            return $this->order($a) <=> $this->order($b);
        });
    }

    private function order(DefinitionSymbol $symbol)
    {
        return [
            $symbol->nameContext->namespace,
            $symbol->nameContext->class,
            self::KIND_ORDER[$symbol->kind],
            $this->isConstructor($symbol) ? 0 : 1,
            $symbol->referencedNames[0],
        ];
    }
}
