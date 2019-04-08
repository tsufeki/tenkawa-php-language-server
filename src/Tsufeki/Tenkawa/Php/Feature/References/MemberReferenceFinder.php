<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\References;

use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\InheritanceTreeTraverser;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedMethod;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedProperty;
use Tsufeki\Tenkawa\Php\Symbol\DefinitionSymbol;
use Tsufeki\Tenkawa\Php\Symbol\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Symbol\MemberSymbol;
use Tsufeki\Tenkawa\Php\Symbol\Symbol;
use Tsufeki\Tenkawa\Php\Symbol\SymbolExtractor;
use Tsufeki\Tenkawa\Php\Symbol\SymbolReflection;
use Tsufeki\Tenkawa\Php\TypeInference\IntersectionType;
use Tsufeki\Tenkawa\Php\TypeInference\ObjectType;
use Tsufeki\Tenkawa\Php\TypeInference\Type;
use Tsufeki\Tenkawa\Php\TypeInference\UnionType;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Exception\DocumentNotOpenException;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\ProgressNotification\ProgressNotificationFeature;
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
     * @var SymbolReflection
     */
    private $symbolReflection;

    /**
     * @var InheritanceTreeTraverser
     */
    private $inheritanceTreeTraverser;

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

    /**
     * @var ProgressNotificationFeature
     */
    private $progressNotificationFeature;

    public function __construct(
        SymbolExtractor $symbolExtractor,
        SymbolReflection $symbolReflection,
        InheritanceTreeTraverser $inheritanceTreeTraverser,
        GlobalReferenceFinder $globalReferenceFinder,
        DocumentStore $documentStore,
        FileReader $fileReader,
        ProgressNotificationFeature $progressNotificationFeature
    ) {
        $this->symbolExtractor = $symbolExtractor;
        $this->symbolReflection = $symbolReflection;
        $this->inheritanceTreeTraverser = $inheritanceTreeTraverser;
        $this->globalReferenceFinder = $globalReferenceFinder;
        $this->documentStore = $documentStore;
        $this->fileReader = $fileReader;
        $this->progressNotificationFeature = $progressNotificationFeature;
    }

    /**
     * @resolve Reference[]
     */
    public function getReferences(Symbol $symbol, bool $includeDeclaration = false): \Generator
    {
        /** @var array<string,string[]> $targetNames member name => class names */
        $targetNames = yield $this->getAllRelatedMembers($symbol);
        /** @var Reference[] $references */
        $references = [];
        /** @var array<string,true> $analyzedUris */
        $analyzedUris = [];
        /** @var Uri[] $uriQueue */
        $uriQueue = yield $this->getClassUris($targetNames, $symbol);
        $progress = $this->progressNotificationFeature->create()->get();
        $progress->set('Finding references...');

        while (!empty($uriQueue)) {
            /** @var Uri $uri */
            $uri = array_pop($uriQueue);
            $uriString = $uri->getNormalized();
            if (isset($analyzedUris[$uriString])) {
                continue;
            }
            $analyzedUris[$uriString] = true;

            yield;
            /** @var Symbol[] $fileSymbols */
            $fileSymbols = yield $this->getSymbolsFromUri($uri);
            foreach ($fileSymbols as $fileSymbol) {
                if ($fileSymbol instanceof DefinitionSymbol && in_array($fileSymbol->kind, GlobalSymbol::KINDS, true)) {
                    $progress->set('Finding references...');
                    yield;
                    /** @var Reference[] $refs */
                    $refs = yield $this->globalReferenceFinder->getReferences($fileSymbol);
                    foreach ($refs as $ref) {
                        $uriQueue[] = $ref->uri;
                    }
                }

                if ($this->checkSymbol($fileSymbol, $targetNames, $symbol->kind, $includeDeclaration)) {
                    $references[] = $this->makeReference($fileSymbol);
                }
            }
        }

        $progress->done();

        return $references;
    }

    /**
     * @resolve array<string,string[]> member name => class names
     */
    public function getAllRelatedMembers(Symbol $symbol): \Generator
    {
        /** @var ResolvedProperty[]|ResolvedClassConst[]|ResolvedMethod[] $topMostMembers */
        $topMostMembers = yield $this->getTopMostMembers($symbol);
        $result = [];

        foreach ($topMostMembers as $member) {
            $visitor = new FindInheritedMembersVisitor([$member]);
            yield $this->inheritanceTreeTraverser->traverse($member->nameContext->class ?? '', [$visitor], $symbol->document);
            $result = array_merge_recursive($result, $visitor->getInheritedMembers());
        }

        return $result;
    }

    /**
     * @resolve Element[]
     */
    private function getTopMostMembers(Symbol $symbol): \Generator
    {
        /** @var Element[] $members */
        $members = yield $this->symbolReflection->getReflectionFromSymbol($symbol);

        return $this->getTopMostMembersFromElements($members);
    }

    /**
     * @return Element[]
     */
    private function getTopMostMembersFromElements(array $members): array
    {
        $result = [];
        foreach ($members as $member) {
            if ($member instanceof ResolvedProperty || $member instanceof ResolvedClassConst || $member instanceof ResolvedMethod) {
                if (empty($member->inheritsFrom)) {
                    $result[] = $member;
                } else {
                    $result = array_merge($result, $this->getTopMostMembersFromElements($member->inheritsFrom));
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string,string[]> $targetNames member name => class names
     *
     * @resolve Uri[]
     */
    private function getClassUris(array $targetNames, Symbol $symbol): \Generator
    {
        $uris = [];
        foreach ($this->getClassSymbols($targetNames, $symbol) as $classSymbol) {
            /** @var Element $class */
            foreach (yield $this->symbolReflection->getReflectionFromSymbol($classSymbol) as $class) {
                if ($class->location !== null) {
                    $uris[] = $class->location->uri;
                }
            }
        }

        return $uris;
    }

    /**
     * @param array<string,string[]> $targetNames member name => class names
     *
     * @return GlobalSymbol[]
     */
    private function getClassSymbols(array $targetNames, Symbol $symbol): array
    {
        if (empty($targetNames)) {
            return [];
        }

        $classNames = array_values(array_unique(array_merge(...array_values($targetNames))));

        return array_map(function (string $class) use ($symbol) {
            $classSymbol = new GlobalSymbol();
            $classSymbol->referencedNames = [$class];
            $classSymbol->kind = GlobalSymbol::CLASS_;
            $classSymbol->document = $symbol->document;
            $classSymbol->nameContext = $symbol->nameContext;

            return $classSymbol;
        }, $classNames);
    }

    /**
     * @return string[]
     */
    private function getClassesFromMemberSymbol(Symbol $symbol): array
    {
        $classes = [];
        if ($symbol instanceof MemberSymbol) {
            $classes = $this->getClassesFromType($symbol->objectType);
        } elseif ($symbol instanceof DefinitionSymbol && in_array($symbol->kind, MemberSymbol::KINDS, true)) {
            $classes = [$symbol->nameContext->class ?? ''];
        }

        return $classes;
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
     * @param array<string,string[]> $targetNames member name => class names
     */
    private function checkSymbol(Symbol $symbol, array $targetNames, $kind, bool $includeDeclaration): bool
    {
        if ($symbol->kind !== $kind) {
            return false;
        }
        if (!$includeDeclaration && $symbol instanceof DefinitionSymbol) {
            return false;
        }

        $classes = $this->getClassesFromMemberSymbol($symbol);
        foreach ($symbol->referencedNames as $name) {
            if ($kind === MemberSymbol::METHOD) {
                $name = strtolower($name);
            }
            if (!empty(array_intersect($classes, $targetNames[$name] ?? []))) {
                return true;
            }
        }

        return false;
    }

    private function makeReference(Symbol $symbol): Reference
    {
        $reference = new Reference();
        $reference->referencedNames = $symbol->referencedNames;
        $reference->uri = $symbol->document->getUri();
        $reference->range = $symbol->range;
        $reference->isDefinition = $symbol instanceof DefinitionSymbol;

        return $reference;
    }
}
