<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Php\Language;

use PHPUnit\Framework\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Php\Language\PhplDiagnosticsProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\ProcessRunner\ReactProcessRunner;
use Tsufeki\Tenkawa\Server\Protocol\Common\DiagnosticSeverity;
use Tsufeki\Tenkawa\Server\Uri;

/**
 * @covers \Tsufeki\Tenkawa\Php\Language\PhplDiagnosticsProvider
 */
class PhplDiagnosticsProviderTest extends TestCase
{
    public function test()
    {
        $document = new Document(Uri::fromString('file:///foo'), 'php');
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
