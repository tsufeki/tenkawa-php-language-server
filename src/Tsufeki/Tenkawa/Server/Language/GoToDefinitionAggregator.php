<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;

class GoToDefinitionAggregator
{
    /**
     * @var GoToDefinitionProvider[]
     */
    private $providers;

    /**
     * @param GoToDefinitionProvider[] $providers
     */
    public function __construct(array $providers)
    {
        $this->providers = $providers;
    }

    /**
     * @resolve Location[]
     */
    public function getLocations(Document $document, Position $position): \Generator
    {
        return array_merge(
            ...yield array_map(function (GoToDefinitionProvider $provider) use ($document, $position) {
                return $provider->getLocations($document, $position);
            }, $this->providers)
        );
    }

    public function hasProviders(): bool
    {
        return !empty($this->providers);
    }
}
