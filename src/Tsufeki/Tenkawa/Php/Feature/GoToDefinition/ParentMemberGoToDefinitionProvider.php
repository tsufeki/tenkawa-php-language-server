<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\GoToDefinition;

use Tsufeki\Tenkawa\Php\Feature\DefinitionSymbol;
use Tsufeki\Tenkawa\Php\Feature\MemberSymbol;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\SymbolReflection;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedMethod;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedProperty;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\GoToDefinition\GoToDefinitionProvider;

class ParentMemberGoToDefinitionProvider implements GoToDefinitionProvider
{
    /**
     * @var SymbolExtractor
     */
    private $symbolExtractor;

    /**
     * @var SymbolReflection
     */
    private $symbolReflection;

    public function __construct(
        SymbolExtractor $symbolExtractor,
        SymbolReflection $symbolReflection
    ) {
        $this->symbolExtractor = $symbolExtractor;
        $this->symbolReflection = $symbolReflection;
    }

    public function getLocations(Document $document, Position $position): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        /** @var Symbol|null */
        $symbol = yield $this->symbolExtractor->getSymbolAt($document, $position);
        if ($symbol === null || !($symbol instanceof DefinitionSymbol) || !in_array($symbol->kind, MemberSymbol::KINDS, true)) {
            return [];
        }

        /** @var (ResolvedMethod|ResolvedProperty|ResolvedClassConst)[] $elements */
        $elements = yield $this->symbolReflection->getReflectionFromSymbol($symbol);
        if ($elements === []) {
            return [];
        }

        return array_values(array_filter(array_map(function (Element $element) {
            return $element->location;
        }, $elements[0]->inheritsFrom)));
    }
}
