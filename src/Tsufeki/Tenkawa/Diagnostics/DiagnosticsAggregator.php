<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Diagnostics;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Event\Document\OnChange;
use Tsufeki\Tenkawa\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Protocol\Common\Diagnostic;
use Tsufeki\Tenkawa\Protocol\LanguageClient;

class DiagnosticsAggregator implements OnOpen, OnChange
{
    /**
     * @var DiagnosticsProvider[]
     */
    private $providers;

    /**
     * @var LanguageClient
     */
    private $client;

    /**
     * @param DiagnosticsProvider[] $providers
     */
    public function __construct(array $providers, LanguageClient $client)
    {
        $this->providers = $providers;
        $this->client = $client;
    }

    public function onOpen(Document $document): \Generator
    {
        /** @var Diagnostic[] $diagnostics */
        $diagnostics = [];
        $version = $document->getVersion();

        yield array_map(
            function (DiagnosticsProvider $provider) use ($document, $version, &$diagnostics) {
                $providerDiagnostics = yield $provider->getDiagnostics($document);

                if ($document->getVersion() === $version && !$document->isClosed()) {
                    $diagnostics = array_merge($diagnostics, $providerDiagnostics);
                    yield $this->client->publishDiagnostics($document->getUri(), $diagnostics);
                }
            },
            $this->providers
        );
    }

    public function onChange(Document $document): \Generator
    {
        yield $this->onOpen($document);
    }
}
