<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Registry;
use Tsufeki\Tenkawa\Diagnostics\DiagnosticsProvider;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Diagnostic;
use Tsufeki\Tenkawa\Protocol\Common\DiagnosticSeverity;
use Tsufeki\Tenkawa\Utils\PositionUtils;

class PhpStanDiagnosticsProvider implements DiagnosticsProvider
{
    /**
     * @var Analyser
     */
    private $analyser;

    /**
     * @var Registry
     */
    private $registry;

    private $severity = DiagnosticSeverity::WARNING;

    public function __construct(Analyser $analyser, Registry $registry)
    {
        $this->analyser = $analyser;
        $this->registry = $registry;
    }

    public function getDiagnostics(Document $document): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        $diagnostics = [];

        yield $this->analyser->analyse(
            $document,
            function (Node $node, Scope $scope) use (&$diagnostics, $document) {
                foreach ($this->registry->getRules(get_class($node)) as $rule) {
                    foreach ($rule->processNode($node, $scope) as $message) {
                        $diag = new Diagnostic();
                        $diag->severity = $this->severity;
                        $diag->source = 'phpstan';
                        $diag->message = $message;
                        $diag->range = PositionUtils::rangeFromNodeAttrs($node->getAttributes(), $document);
                        $diagnostics[] = $diag;
                    }
                }
            }
        );

        return $diagnostics;
    }
}
