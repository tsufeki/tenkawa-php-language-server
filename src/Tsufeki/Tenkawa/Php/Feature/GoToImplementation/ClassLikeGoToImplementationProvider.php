<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\GoToImplementation;

use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\InheritanceTreeTraverser;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassLike;
use Tsufeki\Tenkawa\Php\Symbol\GlobalSymbol;
use Tsufeki\Tenkawa\Php\Symbol\Symbol;
use Tsufeki\Tenkawa\Php\Symbol\SymbolExtractor;
use Tsufeki\Tenkawa\Php\Symbol\SymbolReflection;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\LocationLink;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\GoToImplementation\GoToImplementationProvider;

class ClassLikeGoToImplementationProvider implements GoToImplementationProvider
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
     * @resolve LocationLink[]
     */
    public function getLocations(Document $document, Position $position): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        /** @var Symbol|null */
        $symbol = yield $this->symbolExtractor->getSymbolAt($document, $position);
        if ($symbol === null || $symbol->kind !== GlobalSymbol::CLASS_) {
            return [];
        }

        /** @var ClassLike[] $elements */
        $elements = yield $this->symbolReflection->getReflectionFromSymbol($symbol);
        if ($elements === []) {
            return [];
        }

        $visitor = new FindClassLikeImplementationsVisitor($elements[0]);
        yield $this->inheritanceTreeTraverser->traverse($elements[0]->name, [$visitor], $symbol->document);

        return array_values(array_filter(array_map(function (ResolvedClassLike $element) use ($symbol) {
            return $element->toLocationLink($symbol->range);
        }, $visitor->getImplementations())));
    }
}
