<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Diagnostics;

use PhpParser\Error;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Parser\Ast;
use Tsufeki\Tenkawa\Parser\Parser;
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