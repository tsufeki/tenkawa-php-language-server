<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\References;

use Tsufeki\Tenkawa\Php\Feature\DefinitionSymbol;
use Tsufeki\Tenkawa\Php\Feature\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Feature\MemberSymbol;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolExtractor;
use Tsufeki\Tenkawa\Php\TypeInference\IntersectionType;
use Tsufeki\Tenkawa\Php\TypeInference\ObjectType;
use Tsufeki\Tenkawa\Php\TypeInference\Type;
use Tsufeki\Tenkawa\Php\TypeInference\UnionType;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Exception\DocumentNotOpenException;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Io\FileReader;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class MemberReferenceFinder implements ReferenceFinder
{
    /**
     * @var SymbolExtractor
     */
    private $symbolExtractor;

    /**
     * @var GlobalReferenceFinder
     */
    private $globalReferenceFinder;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var FileReader
     */
    private $fileReader;

    const GLOBAL_KINDS = [
        GlobalSymbol::CLASS_,
        GlobalSymbol::FUNCTION_,
        GlobalSymbol::CONST_,
    ];

    const MEMBER_KINDS = [
        MemberSymbol::CLASS_CONST,
        MemberSymbol::PROPERTY,
        MemberSymbol::METHOD,
    ];

    public function __construct(
        SymbolExtractor $symbolExtractor,
        GlobalReferenceFinder $globalReferenceFinder,
        DocumentStore $documentStore,
        FileReader $fileReader
    ) {
        $this->symbolExtractor = $symbolExtractor;
        $this->globalReferenceFinder = $globalReferenceFinder;
        $this->documentStore = $documentStore;
        $this->fileReader = $fileReader;
    }

    /**
     * @resolve Reference[]
     */
    public function getReferences(Symbol $symbol, bool $includeDeclaration = false): \Generator
    {
        // TODO includeDeclaration
        if (!($symbol instanceof MemberSymbol)) {
            return [];
        }

        /** @var Reference[] $references */
        $references = [];
        /** @var array<string,bool> $analyzedSymbols */
        $analyzedSymbols = [];
        /** @var array<string,bool> $analyzedUris */
        $analyzedUris = [];
        /** @var (GlobalSymbol|DefinitionSymbol)[] $currentSymbols */
        $currentSymbols = $this->getClassSymbolsFromMemberSymbol($symbol);

        while (!empty($currentSymbols)) {
            /** @var GlobalSymbol|DefinitionSymbol $currentSymbol */
            $currentSymbol = array_pop($currentSymbols);
            if (isset($analyzedSymbols[$currentSymbol->referencedNames[0]])) {
                continue;
            }
            $analyzedSymbols[$currentSymbol->referencedNames[0]] = true;

            yield;
            /** @var Reference[] $globalRefs */
            $globalRefs = yield $this->globalReferenceFinder->getReferences($currentSymbol, true);
            foreach ($globalRefs as $globalRef) {
                $uriString = $globalRef->uri->getNormalized();
                if (isset($analyzedUris[$uriString])) {
                    continue;
                }
                $analyzedUris[$uriString] = true;

                yield;
                /** @var Symbol[] $fileSymbols */
                $fileSymbols = yield $this->getSymbolsFromUri($globalRef->uri);
                $this->analyzeSymbols($symbol, $fileSymbols, $references, $currentSymbols);
            }
        }

        return $references;
    }

    /**
     * @return GlobalSymbol[]
     */
    private function getClassSymbolsFromMemberSymbol(MemberSymbol $symbol): array
    {
        return array_map(function (string $class) use ($symbol) {
            $classSymbol = new GlobalSymbol();
            $classSymbol->referencedNames = [$class];
            $classSymbol->kind = GlobalSymbol::CLASS_;
            $classSymbol->document = $symbol->document;
            $classSymbol->nameContext = $symbol->nameContext;

            return $classSymbol;
        }, $this->getClassesFromType($symbol->objectType));
    }

    /**
     * @return string[]
     */
    private function getClassesFromType(Type $type): array
    {
        if ($type instanceof ObjectType) {
            return [$type->class];
        }

        if ($type instanceof IntersectionType || $type instanceof UnionType) {
            return array_merge(...array_map(function (Type $subtype) {
                return $this->getClassesFromType($subtype);
            }, $type->types));
        }

        return [];
    }

    /**
     * @resolve Symbol[]
     */
    private function getSymbolsFromUri(Uri $uri): \Generator
    {
        try {
            $document = $this->documentStore->get($uri);
        } catch (DocumentNotOpenException $e) {
            $text = yield $this->fileReader->read($uri);
            $document = yield $this->documentStore->load($uri, 'php', $text);
        }

        $wholeDocumentRange = new Range(
            PositionUtils::positionFromOffset(0, $document),
            PositionUtils::positionFromOffset(strlen($document->getText()), $document)
        );

        return yield $this->symbolExtractor->getSymbolsInRange($document, $wholeDocumentRange);
    }

    /**
     * @param Symbol[]                          $symbols
     * @param Reference[]                       $references
     * @param (GlobalSymbol|DefinitionSymbol)[] $currentSymbols
     */
    private function analyzeSymbols(
        MemberSymbol $needleSymbol,
        array $symbols,
        array &$references,
        array &$currentSymbols
    ) {
        foreach ($symbols as $symbol) {
            if ($symbol instanceof DefinitionSymbol && in_array($symbol->kind, self::GLOBAL_KINDS, true)) {
                $currentSymbols[] = $symbol;
            }

            if ($this->checkSymbol($needleSymbol, $symbol)) {
                $references[] = $this->makeReference($symbol);
            }
        }
    }

    private function checkSymbol(MemberSymbol $needleSymbol, Symbol $symbol): bool
    {
        // TODO inheritance
        if ($needleSymbol->referencedNames !== $symbol->referencedNames) {
            return false;
        }

        if ($symbol instanceof MemberSymbol) {
            $classes = $this->getClassesFromType($symbol->objectType);
        } elseif ($symbol instanceof DefinitionSymbol && in_array($symbol->kind, self::MEMBER_KINDS, true)) {
            $classes = [$symbol->nameContext->class ?? ''];
        } else {
            return false;
        }

        return !empty(array_intersect($classes, $this->getClassesFromType($needleSymbol->objectType)));
    }

    private function makeReference(Symbol $symbol): Reference
    {
        $reference = new Reference();
        $reference->referencedNames = $symbol->referencedNames;
        $reference->uri = $symbol->document->getUri();
        $reference->range = $symbol->range;

        return $reference;
    }
}
