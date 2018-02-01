<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Server\Language;

use PHPUnit\Framework\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Language\DiagnosticsAggregator;
use Tsufeki\Tenkawa\Server\Language\DiagnosticsProvider;
use Tsufeki\Tenkawa\Server\Protocol\Common\Diagnostic;
use Tsufeki\Tenkawa\Server\Protocol\LanguageClient;
use Tsufeki\Tenkawa\Server\Uri;

/**
 * @covers \Tsufeki\Tenkawa\Server\Language\DiagnosticsAggregator
 */
class DiagnosticsAggregatorTest extends TestCase
{
    public function test()
    {
        $project = new Project(Uri::fromString('file:///'));
        $document = new Document(Uri::fromString('file:///foo'), 'php', $project);

        $diags = [];
        $providers = [];

        foreach (range(0, 1) as $i) {
            $diag = new Diagnostic();
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

        $client = $this->createMock(LanguageClient::class);
        $client
            ->expects($this->exactly(2))
            ->method('publishDiagnostics')
            ->withConsecutive(
                [$this->identicalTo($document->getUri()), $this->identicalTo([$diags[0]])],
                [$this->identicalTo($document->getUri()), $this->identicalTo($diags)]
            );

        $aggregator = new DiagnosticsAggregator($providers, $client);

        ReactKernel::start(function () use ($aggregator, $document) {
            yield $aggregator->onChange($document);
        });
    }
}
