<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\GoToImplementation;

use Tsufeki\Tenkawa\Php\Feature\MemberSymbol;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\SymbolReflection;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\InheritanceTreeTraverser;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedMethod;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedProperty;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\GoToImplementation\GoToImplementationProvider;

class MemberGoToImplementationProvider implements GoToImplementationProvider
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

    public function __construct(
        SymbolExtractor $symbolExtractor,
        SymbolReflection $symbolReflection,
        InheritanceTreeTraverser $inheritanceTreeTraverser
    ) {
        $this->symbolExtractor = $symbolExtractor;
        $this->symbolReflection = $symbolReflection;
        $this->inheritanceTreeTraverser = $inheritanceTreeTraverser;
    }

    /**
     * @resolve Location[]
     */
    public function getLocations(Document $document, Position $position): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        /** @var Symbol|null */
        $symbol = yield $this->symbolExtractor->getSymbolAt($document, $position);
        if ($symbol === null || !in_array($symbol->kind, MemberSymbol::KINDS, true)) {
            return [];
        }

        /** @var (ResolvedMethod|ResolvedProperty|ResolvedClassConst)[] $elements */
        $elements = yield $this->symbolReflection->getReflectionFromSymbol($symbol);
        if ($elements === []) {
            return [];
        }

        $visitor = new FindMemberImplementationsVisitor([$elements[0]]);
        yield $this->inheritanceTreeTraverser->traverse($elements[0]->nameContext->class ?? '', [$visitor], $symbol->document);

        return array_values(array_filter(array_map(function (Element $element) {
            return $element->location;
        }, $visitor->getImplementations())));
    }
}
