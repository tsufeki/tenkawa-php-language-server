<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Diagnostics;

use Psr\Log\LoggerInterface;
use Recoil\Recoil;
use Recoil\Strand;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Event\Document\OnChange;
use Tsufeki\Tenkawa\Server\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Server\Event\OnIndexingFinished;
use Tsufeki\Tenkawa\Server\Exception\CancelledException;
use Tsufeki\Tenkawa\Server\Exception\DocumentNotOpenException;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ClientCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Capabilities\ServerCapabilities;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class DiagnosticsFeature implements Feature, OnOpen, OnChange, OnIndexingFinished
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
     * @var Strand|null
     */
    private $pendingWorkspaceTask;

    /**
     * @var float
     */
    private $debounceTime;

    /**
     * @var MappedJsonRpc
     */
    private $rpc;

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
        DocumentStore $documentStore,
        MappedJsonRpc $rpc,
        LoggerInterface $logger
    ) {
        $this->providers = $providers;
        $this->workspaceProviders = $workspaceProviders;
        $this->documentStore = $documentStore;
        $this->debounceTime = 0.9;
        $this->rpc = $rpc;
        $this->logger = $logger;
    }

    public function initialize(ClientCapabilities $clientCapabilities, ServerCapabilities $serverCapabilities): \Generator
    {
        return;
        yield;
    }

    /**
     * Diagnostics notification are sent from the server to the client to
     * signal results of validation runs.
     *
     * Diagnostics are "owned" by the server so it is the server’s
     * responsibility to clear them if necessary. The following rule is used
     * for VS Code servers that generate diagnostics:
     *
     *  - if a language is single file only (for example HTML) then diagnostics
     *    are cleared by the server when the file is closed.
     *  - if a language has a project system (for example C#) diagnostics are
     *    not cleared when a file closes. When a project is opened all
     *    diagnostics for all files are recomputed (or read from a cache).
     *
     * When a file changes it is the server’s responsibility to re-compute
     * diagnostics and push them to the client. If the computed set is empty it
     * has to push the empty array to clear former diagnostics. Newly pushed
     * diagnostics always replace previously pushed diagnostics. There is no
     * merging that happens on the client side.
     *
     * @param Uri          $uri         The URI for which diagnostic information is reported.
     * @param Diagnostic[] $diagnostics An array of diagnostic information items.
     */
    public function publishDiagnostics(Uri $uri, array $diagnostics): \Generator
    {
        // $count = count($diagnostics);
        // $this->logger->debug('send: ' . __FUNCTION__ . " $uri [$count items]");
        yield $this->rpc->notify('textDocument/publishDiagnostics', compact('uri', 'diagnostics'));
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
            $this->logger->debug('Workspace diagnostics starting');

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
            $this->logger->debug('Workspace diagnostics cancelled [' . ($time ?? '') . ']');
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
        foreach ($diagnostics as $uriDiagnostics) {
            foreach ($uriDiagnostics as $diag) {
                $this->fixDiagnostic($diag);
            }
        }

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

                yield $this->publishDiagnostics(
                    $document->getUri(),
                    array_merge(...array_values($documentDiags))
                );
            } catch (DocumentNotOpenException $e) {
            }
        }
    }

    private function fixDiagnostic(Diagnostic $diagnostic)
    {
        // Multiline squiggles are somewhat annoying.
        $range = $diagnostic->range;
        if ($range->start->line !== $range->end->line) {
            $range->end->line = $range->start->line + 1;
            $range->end->character = 0;
        }
    }
}
