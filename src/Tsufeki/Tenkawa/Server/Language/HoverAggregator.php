<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\Hover;

class HoverAggregator
{
    /**
     * @var HoverProvider[]
     */
    private $providers;

    /**
     * @param HoverProvider[] $providers
     */
    public function __construct(array $providers)
    {
        $this->providers = $providers;
    }

    /**
     * @resolve Hover|null
     */
    public function getHover(Document $document, Position $position): \Generator
    {
        foreach ($this->providers as $provider) {
            $hover = yield $provider->getHover($document, $position);
            if ($hover !== null) {
                return $hover;
            }
        }

        return null;
    }

    public function hasProviders(): bool
    {
        return !empty($this->providers);
    }
}
