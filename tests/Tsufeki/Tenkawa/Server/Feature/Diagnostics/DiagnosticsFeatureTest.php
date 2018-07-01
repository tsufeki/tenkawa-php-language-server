<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Server\Feature\Diagnostics;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Recoil\React\ReactKernel;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\Diagnostic;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\DiagnosticsFeature;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\DiagnosticsProvider;
use Tsufeki\Tenkawa\Server\Uri;

/**
 * @covers \Tsufeki\Tenkawa\Server\Feature\Diagnostics\DiagnosticsFeature
 */
class DiagnosticsFeatureTest extends TestCase
{
    public function test_document_diagnostics()
    {
        $document = new Document(Uri::fromString('file:///foo'), 'php');

        $diags = [];
        $providers = [];

        foreach (range(0, 1) as $i) {
            $diag = new Diagnostic();
            $diag->range = new Range(new Position(0, 0), new Position(0, 0));
            $provider = $this->createMock(DiagnosticsProvider::class);
            $provider
                ->expects($this->once())
                ->method('getDiagnostics')
                ->with($this->identicalTo($document))
                ->willReturn((function () use ($diag) {
                    return [$diag];
                    yield;
                })());
            $diags[] = $diag;
            $providers[] = $provider;
        }

        $client = $this->createMock(MappedJsonRpc::class);
        $client
            ->expects($this->exactly(2))
            ->method('notify')
            ->withConsecutive(
                [
                    $this->identicalTo('textDocument/publishDiagnostics'),
                    $this->identicalTo(['uri' => $document->getUri(), 'diagnostics' => [$diags[0]]]),
                ],
                [
                    $this->identicalTo('textDocument/publishDiagnostics'),
                    $this->identicalTo(['uri' => $document->getUri(), 'diagnostics' => $diags]),
                ]
            );

        $documentStore = $this->createMock(DocumentStore::class);
        $documentStore
            ->expects($this->exactly(2))
            ->method('getDocuments')
            ->willReturn((function () use ($document) {
                return [$document];
                yield;
            })());
        $logger = $this->createMock(LoggerInterface::class);

        $diagnosticsFeature = new DiagnosticsFeature($providers, [], $documentStore, $client, $logger);

        ReactKernel::start(function () use ($diagnosticsFeature, $document) {
            yield $diagnosticsFeature->onChange($document);
        });
    }
}
