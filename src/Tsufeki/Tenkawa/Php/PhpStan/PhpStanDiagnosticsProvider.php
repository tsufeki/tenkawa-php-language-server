<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Registry;
use Tsufeki\Tenkawa\Php\PhpStan\Analyser\Analyser;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Exception\DocumentNotOpenException;
use Tsufeki\Tenkawa\Server\Feature\Configuration\ConfigurationFeature;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\Diagnostic;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\DiagnosticSeverity;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\WorkspaceDiagnosticsProvider;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class PhpStanDiagnosticsProvider implements WorkspaceDiagnosticsProvider
{
    /**
     * @var Analyser
     */
    private $analyser;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var ConfigurationFeature
     */
    private $configurationFeature;

    private $severity = DiagnosticSeverity::WARNING;

    public function __construct(
        Analyser $analyser,
        Registry $registry,
        DocumentStore $documentStore,
        ConfigurationFeature $configurationFeature
    ) {
        $this->analyser = $analyser;
        $this->registry = $registry;
        $this->documentStore = $documentStore;
        $this->configurationFeature = $configurationFeature;
    }

    /**
     * @param Document[] $documents
     *
     * @resolve array<string,Diagnostic[]> URI => diagnostics
     */
    public function getWorkspaceDiagnostics(array $documents): \Generator
    {
        $diagnostics = [];
        foreach ($documents as $document) {
            yield;
            $diagnostics = array_merge_recursive($diagnostics, yield $this->getDiagnostics($document));
        }

        return $diagnostics;
    }

    /**
     * @resolve array<string,Diagnostic[]> URI => diagnostics
     */
    private function getDiagnostics(Document $document): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return [];
        }

        $enabled = yield $this->configurationFeature->get('diagnostics.phpstan.enabled', $document);
        if ($enabled === false) {
            return [];
        }

        $diagnostics = [];

        yield $this->analyser->analyse(
            $document,
            function (Node $node, Scope $scope) use (&$diagnostics) {
                foreach ($this->registry->getRules(get_class($node)) as $rule) {
                    foreach ($rule->processNode($node, $scope) as $message) {
                        try {
                            $uri = Uri::fromFilesystemPath($scope->getFile());
                            $document = $this->documentStore->get($uri);
                            $diag = new Diagnostic();
                            $diag->severity = $this->severity;
                            $diag->source = 'phpstan';
                            $diag->message = $message;
                            $diag->range = PositionUtils::rangeFromNodeAttrs($node->getAttributes(), $document);
                            $diagnostics[$uri->getNormalized()][] = $diag;
                        } catch (DocumentNotOpenException $e) {
                        }
                    }
                }
            }
        );

        return $diagnostics;
    }
}
