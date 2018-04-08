<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Parser;

use PhpParser\Error;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\Diagnostic;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\DiagnosticSeverity;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\DiagnosticsProvider;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

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
            return [];
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
