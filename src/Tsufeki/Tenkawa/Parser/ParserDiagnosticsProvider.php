<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Parser;

use PhpParser\Error;
use Tsufeki\Tenkawa\Diagnostics\DiagnosticsProvider;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Diagnostic;
use Tsufeki\Tenkawa\Protocol\Common\DiagnosticSeverity;
use Tsufeki\Tenkawa\Utils\PositionUtils;

class ParserDiagnosticsProvider implements DiagnosticsProvider
{
    /**
     * @var Parser
     */
    private $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function getDiagnostics(Document $document): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return;
        }

        /** @var Ast $ast */
        $ast = yield $this->parser->parse($document);

        return array_map(function (Error $error) use ($document) {
            $diag = new Diagnostic();
            $diag->range = PositionUtils::rangeFromNodeAttrs($error->getAttributes(), $document);
            $diag->severity = DiagnosticSeverity::ERROR;
            $diag->source = 'php';
            $diag->message = $error->getRawMessage();

            return $diag;
        }, $ast->errors);
    }
}
