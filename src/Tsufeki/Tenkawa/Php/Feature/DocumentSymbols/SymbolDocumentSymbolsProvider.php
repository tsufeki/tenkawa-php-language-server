<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\DocumentSymbols;

use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;
use Tsufeki\Tenkawa\Php\Symbol\DefinitionSymbol;
use Tsufeki\Tenkawa\Php\Symbol\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Symbol\MemberSymbol;
use Tsufeki\Tenkawa\Php\Symbol\SymbolExtractor;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\DocumentSymbolClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Common\Location;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Common\SymbolInformation;
use Tsufeki\Tenkawa\Server\Feature\Common\SymbolKind;
use Tsufeki\Tenkawa\Server\Feature\DocumentSymbols\DocumentSymbol;
use Tsufeki\Tenkawa\Server\Feature\DocumentSymbols\DocumentSymbolsProvider;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class SymbolDocumentSymbolsProvider implements DocumentSymbolsProvider
{
    /**
     * @var SymbolExtractor
     */
    private $symbolExtractor;

    private const KINDS = [
        GlobalSymbol::CLASS_ => SymbolKind::CLASS_,
        GlobalSymbol::FUNCTION_ => SymbolKind::FUNCTION_,
        GlobalSymbol::CONST_ => SymbolKind::CONSTANT,
        MemberSymbol::PROPERTY => SymbolKind::PROPERTY,
        MemberSymbol::CLASS_CONST => SymbolKind::CONSTANT,
        MemberSymbol::METHOD => SymbolKind::METHOD,
    ];

    private const KIND_ORDER = [
        GlobalSymbol::CONST_ => 1,
        GlobalSymbol::FUNCTION_ => 2,
        GlobalSymbol::CLASS_ => 3,
        MemberSymbol::CLASS_CONST => 4,
        MemberSymbol::PROPERTY => 5,
        MemberSymbol::METHOD => 6,
    ];

    public function __construct(SymbolExtractor $symbolExtractor)
    {
        $this->symbolExtractor = $symbolExtractor;
    }

    public function getSymbols(Document $document, DocumentSymbolClientCapabilities $capabilities): \Generator
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

        return $capabilities->hierarchicalDocumentSymbolSupport
            ? $this->getDefinitionSymbols($symbols)
            : $this->getSymbolInformation($symbols);
    }

    /**
     * @param DefinitionSymbol[] $symbols
     *
     * @return SymbolInformation[]
     */
    private function getSymbolInformation(array $symbols): array
    {
        /** @var SymbolInformation[] $symbolInfos */
        $symbolInfos = [];

        $this->sort($symbols);

        foreach ($symbols as $symbol) {
            $symbolInfo = new SymbolInformation();
            $symbolInfo->name = StringUtils::getShortName($symbol->referencedNames[0]);

            switch ($symbol->kind) {
                case GlobalSymbol::CLASS_:
                case GlobalSymbol::FUNCTION_:
                case GlobalSymbol::CONST_:
                    $symbolInfo->containerName = ltrim($symbol->nameContext->namespace, '\\');
                    break;
                case MemberSymbol::PROPERTY:
                case MemberSymbol::CLASS_CONST:
                case MemberSymbol::METHOD:
                    $symbolInfo->containerName = StringUtils::getShortName($symbol->nameContext->class ?? '');
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
            $symbolInfo->location->uri = $symbol->document->getUri();
            $symbolInfo->location->range = $symbol->definitionRange;
            $symbolInfos[] = $symbolInfo;
        }

        return $symbolInfos;
    }

    /**
     * @param DefinitionSymbol[] $symbols
     *
     * @return DocumentSymbol[]
     */
    private function getDefinitionSymbols(array $symbols): array
    {
        /** @var DocumentSymbol[] $documentSymbols */
        $documentSymbols = [];
        /** @var DocumentSymbol|null $class */
        $class = null;

        $this->sort($symbols);

        foreach ($symbols as $symbol) {
            $documentSymbol = new DocumentSymbol();
            $documentSymbol->name = StringUtils::getShortName($symbol->referencedNames[0]);
            $documentSymbol->kind = self::KINDS[$symbol->kind];
            $documentSymbol->range = $symbol->definitionRange;
            $documentSymbol->selectionRange = $symbol->range;

            if (in_array($symbol->kind, MemberSymbol::KINDS, true) && $class !== null) {
                $class->children[] = $documentSymbol;
            } else {
                $documentSymbols[] = $documentSymbol;
            }

            if ($symbol->kind === GlobalSymbol::CLASS_) {
                $class = $documentSymbol;
            }
        }

        return $documentSymbols;
    }

    private function isConstructor(DefinitionSymbol $symbol): bool
    {
        return $symbol->kind === MemberSymbol::METHOD && in_array(strtolower($symbol->referencedNames[0]), ['__construct', '__destruct']);
    }

    /**
     * @param DefinitionSymbol[] $symbols
     */
    private function sort(array &$symbols): void
    {
        usort($symbols, function (DefinitionSymbol $a, DefinitionSymbol $b) {
            return $this->order($a) <=> $this->order($b);
        });
    }

    private function order(DefinitionSymbol $symbol): array
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
