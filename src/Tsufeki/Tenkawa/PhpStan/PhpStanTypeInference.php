<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\TypeSpecifier;
use React\Promise\Deferred;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\TypeInference\TypeInference;
use Tsufeki\Tenkawa\Utils\SyncAsync;

class PhpStanTypeInference implements TypeInference
{
    /**
     * @var NodeScopeResolver
     */
    private $nodeScopeResolver;

    /**
     * @var DocumentParser
     */
    private $parser;

    /**
     * @var IndexBroker
     */
    private $broker;

    /**
     * @var Standard
     */
    private $printer;

    /**
     * @var TypeSpecifier
     */
    private $typeSpecifier;

    /**
     * @var SyncAsync
     */
    private $syncAsync;

    public function __construct(
        NodeScopeResolver $nodeScopeResolver,
        DocumentParser $parser,
        IndexBroker $broker,
        Standard $printer,
        TypeSpecifier $typeSpecifier,
        SyncAsync $syncAsync
    ) {
        $this->nodeScopeResolver = $nodeScopeResolver;
        $this->parser = $parser;
        $this->broker = $broker;
        $this->printer = $printer;
        $this->typeSpecifier = $typeSpecifier;
        $this->syncAsync = $syncAsync;
    }

    public function infer(Document $document): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return;
        }

        $promise = $document->get('type_inference');
        if ($promise !== null) {
            return yield $promise;
        }

        $deferred = new Deferred();
        $document->set('type_inference', $deferred->promise());
        $path = $document->getUri()->getFilesystemPath();

        yield $this->syncAsync->callSync(
            function () use ($path) {
                $this->nodeScopeResolver->processNodes(
                    $this->parser->parseFile($path),
                    new Scope($this->broker, $this->printer, $this->typeSpecifier, $path),
                    function (Node $node, Scope $scope) {
                        if ($node instanceof Node\Expr && !($node instanceof Node\Expr\Error)) {
                            $type = $scope->getType($node);
                            $node->setAttribute('type', new PhpStanType($type));
                        }
                    }
                );
            },
            [],
            function () use ($document, $path) {
                $this->broker->setDocument($document);
                $this->parser->setDocument($document);
                $this->nodeScopeResolver->setAnalysedFiles([$path]);
            },
            function () {
                $this->broker->setDocument(null);
                $this->parser->setDocument(null);
                $this->nodeScopeResolver->setAnalysedFiles([]);
            }
        );

        $deferred->resolve();
    }
}
