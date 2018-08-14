<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\References;

use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Php\Feature\SymbolExtractor;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Location;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\References\ReferenceContext;
use Tsufeki\Tenkawa\Server\Feature\References\ReferencesProvider;

class SymbolReferencesProvider implements ReferencesProvider
{
    /**
     * @var SymbolExtractor
     */
    private $symbolExtractor;

    /**
     * @var ReferenceFinder[]
     */
    private $referenceFinders;

    /**
     * @param ReferenceFinder[] $referenceFinders
     */
    public function __construct(
        SymbolExtractor $symbolExtractor,
        array $referenceFinders
    ) {
        $this->symbolExtractor = $symbolExtractor;
        $this->referenceFinders = $referenceFinders;
    }

    /**
     * @resolve Location[]
     */
    public function getReferences(Document $document, Position $position, ?ReferenceContext $context): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        $includeDeclaration = $context && $context->includeDeclaration;

        /** @var Symbol|null */
        $symbol = yield $this->symbolExtractor->getSymbolAt($document, $position);
        if ($symbol === null) {
            return [];
        }

        /** @var Reference[] $references */
        $references = array_merge(...yield array_map(function (ReferenceFinder $finder) use ($symbol, $includeDeclaration) {
            return $finder->getReferences($symbol, $includeDeclaration);
        }, $this->referenceFinders));

        /** @var Location[] $locations */
        $locations = array_map(function (Reference $reference) {
            $location = new Location();
            $location->uri = $reference->uri;
            $location->range = $reference->range;

            return $location;
        }, $references);

        return $locations;
    }
}
