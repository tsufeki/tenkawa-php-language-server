<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Range;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CodeActionContext;

class CodeActionAggregator
{
    /**
     * @var CodeActionProvider[]
     */
    private $providers;

    /**
     * @param CodeActionProvider[] $providers
     */
    public function __construct(array $providers)
    {
        $this->providers = $providers;
    }

    /**
     * @resolve Command[]
     */
    public function getCodeActions(Document $document, Range $range, CodeActionContext $context): \Generator
    {
        return array_merge(
            ...yield array_map(function (CodeActionProvider $provider) use ($document, $range, $context) {
                return $provider->getCodeActions($document, $range, $context);
            }, $this->providers)
        );
    }

    public function hasProviders(): bool
    {
        return !empty($this->providers);
    }
}
