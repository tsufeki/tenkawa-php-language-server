<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Diagnostics;

use PHPUnit\Framework\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Diagnostics\PhplDiagnosticsProvider;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Document\Project;
use Tsufeki\Tenkawa\ProcessRunner\ReactProcessRunner;
use Tsufeki\Tenkawa\Protocol\Common\DiagnosticSeverity;
use Tsufeki\Tenkawa\Uri;

/**
 * @covers \Tsufeki\Tenkawa\Diagnostics\PhplDiagnosticsProvider
 */
class PhplDiagnosticsProviderTest extends TestCase
{
    public function test()
    {
        $project = new Project(Uri::fromString('file:///'));
        $document = new Document(Uri::fromString('file:///foo'), 'php', $project);
        $document->update('<?php foo(');
        $processRunner = new ReactProcessRunner();
        $provider = new PhplDiagnosticsProvider($processRunner);

        ReactKernel::start(function () use ($provider, $document) {
            $diags = yield $provider->getDiagnostics($document);
            $this->assertCount(1, $diags);
            $this->assertSame('syntax error, unexpected end of file', $diags[0]->message);
            $this->assertSame(DiagnosticSeverity::ERROR, $diags[0]->severity);
            $this->assertSame(0, $diags[0]->range->start->line);
        });
    }
}
