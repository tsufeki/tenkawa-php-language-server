<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

use Psr\Log\LoggerInterface;
use Recoil\Recoil;
use Recoil\Strand;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Event\Document\OnChange;
use Tsufeki\Tenkawa\Server\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Server\Event\OnIndexingFinished;
use Tsufeki\Tenkawa\Server\Exception\CancelledException;
use Tsufeki\Tenkawa\Server\Exception\DocumentNotOpenException;
use Tsufeki\Tenkawa\Server\Protocol\Common\Diagnostic;
use Tsufeki\Tenkawa\Server\Protocol\LanguageClient;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class DiagnosticsAggregator implements OnOpen, OnChange, OnIndexingFinished
{
    /**
     * @var DiagnosticsProvider[]
     */
    private $providers;

    /**
     * @var WorkspaceDiagnosticsProvider[]
     */
    private $workspaceProviders;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var LanguageClient
     */
    private $client;

    /**
     * @var Strand|null
     */
    private $pendingWorkspaceTask;

    /**
     * @var float
     */
    private $debounceTime;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param DiagnosticsProvider[]          $providers
     * @param WorkspaceDiagnosticsProvider[] $workspaceProviders
     */
    public function __construct(
        array $providers,
        array $workspaceProviders,
        LanguageClient $client,
        DocumentStore $documentStore,
        LoggerInterface $logger
    ) {
        $this->providers = $providers;
        $this->workspaceProviders = $workspaceProviders;
        $this->client = $client;
        $this->documentStore = $documentStore;
        $this->debounceTime = 3.0;
        $this->logger = $logger;
    }

    public function onOpen(Document $document): \Generator
    {
        yield array_map(
            function (DiagnosticsProvider $provider) use ($document) {
                $diagnostics = yield $provider->getDiagnostics($document);
                $uriString = $document->getUri()->getNormalized();
                yield $this->sendDiagnostics($provider, [$uriString => $diagnostics]);
            },
            $this->providers
        );
    }

    public function onChange(Document $document): \Generator
    {
        yield $this->onOpen($document);
    }

    public function onIndexingFinished(): \Generator
    {
        if ($this->pendingWorkspaceTask !== null) {
            $this->pendingWorkspaceTask->throw(new CancelledException());
        }

        $this->pendingWorkspaceTask = yield Recoil::strand();

        try {
            yield Recoil::sleep($this->debounceTime);

            $time = new Stopwatch();

            $documents = yield $this->documentStore->getDocuments();
            yield array_map(
                function (WorkspaceDiagnosticsProvider $provider) use ($documents) {
                    $diagnostics = yield $provider->getWorkspaceDiagnostics($documents);
                    yield $this->sendDiagnostics($provider, $diagnostics);
                },
                $this->workspaceProviders
            );

            $this->logger->debug("Workspace diagnostics finished [$time]");
        } catch (CancelledException $e) {
            return;
        } finally {
            $this->pendingWorkspaceTask = null;
        }
    }

    /**
     * @param DiagnosticsProvider|WorkspaceDiagnosticsProvider $provider
     * @param array<string,Diagnostic[]>                       $diagnostics URI => diagnostics
     */
    private function sendDiagnostics($provider, array $diagnostics): \Generator
    {
        /** @var Document[] $documents */
        $documents = yield $this->documentStore->getDocuments();
        $providerId = spl_object_hash($provider);
        foreach ($documents as $document) {
            try {
                $uriString = $document->getUri()->getNormalized();
                /** @var array<string,Diagnostic[]> $documentDiags */
                $documentDiags = $document->get('diagnostics') ?? [];
                $documentDiags[$providerId] = $diagnostics[$uriString] ?? [];
                $document->set('diagnostics', $documentDiags);

                yield $this->client->publishDiagnostics(
                    $document->getUri(),
                    array_merge(...array_values($documentDiags))
                );
            } catch (DocumentNotOpenException $e) {
            }
        }
    }
}
