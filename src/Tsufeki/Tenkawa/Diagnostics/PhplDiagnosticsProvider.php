<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Diagnostics;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\ProcessRunner\ProcessResult;
use Tsufeki\Tenkawa\ProcessRunner\ProcessRunner;
use Tsufeki\Tenkawa\Protocol\Common\Diagnostic;
use Tsufeki\Tenkawa\Protocol\Common\DiagnosticSeverity;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Common\Range;

class PhplDiagnosticsProvider implements DiagnosticsProvider
{
    /**
     * @var ProcessRunner
     */
    private $processRunner;

    const MESSAGE_REGEX = '~^(?:(?:PHP )?Fatal error: )?(.+?)(?: in - on line ([0-9]+))?$~m';

    public function __construct(ProcessRunner $processRunner)
    {
        $this->processRunner = $processRunner;
    }

    public function getDiagnostics(Document $document): \Generator
    {
        $cmd = [
            'php',
            '-n',
            '-d', 'error_reporting=E_ALL',
            '-d', 'display_errors=stderr',
            '-l',
        ];

        /** @var ProcessResult $result */
        $result = yield $this->processRunner->run($cmd, $document->getText());
        $diagnostics = [];

        if ($result->exitCode !== 0 && $result->exitCode !== null && !empty(trim($result->stderr))) {
            if (preg_match(self::MESSAGE_REGEX, trim($result->stderr), $matches)) {
                $diag = new Diagnostic();
                $diag->severity = DiagnosticSeverity::ERROR;
                $diag->source = 'php -l';
                $diag->message = $matches[1];
                $diag->range = new Range(
                    new Position(max(0, (int)$matches[2] - 1), 0),
                    new Position(max(0, (int)$matches[2] - 1), 999)
                );
                $diagnostics[] = $diag;
            }
        }

        return $diagnostics;
    }
}
