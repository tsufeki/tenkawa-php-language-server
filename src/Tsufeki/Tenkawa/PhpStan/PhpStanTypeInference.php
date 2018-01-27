<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Type\IntersectionType as PhpStanIntersectionType;
use PHPStan\Type\Type as PhpStanType;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\UnionType as PhpStanUnionType;
use React\Promise\Deferred;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\TypeInference\BasicType;
use Tsufeki\Tenkawa\TypeInference\IntersectionType;
use Tsufeki\Tenkawa\TypeInference\ObjectType;
use Tsufeki\Tenkawa\TypeInference\Type;
use Tsufeki\Tenkawa\TypeInference\TypeInference;
use Tsufeki\Tenkawa\TypeInference\UnionType;
use Tsufeki\Tenkawa\Utils\SyncAsyncKernel;

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
     * @var PhpDocResolver
     */
    private $phpDocResolver;

    /**
     * @var Standard
     */
    private $printer;

    /**
     * @var TypeSpecifier
     */
    private $typeSpecifier;

    /**
     * @var SyncAsyncKernel
     */
    private $syncAsync;

    const IGNORED_EXPR_NODES = [
        Expr\Error::class => true,
        Scalar\EncapsedStringPart::class => true,
    ];

    public function __construct(
        NodeScopeResolver $nodeScopeResolver,
        DocumentParser $parser,
        IndexBroker $broker,
        PhpDocResolver $phpDocResolver,
        Standard $printer,
        TypeSpecifier $typeSpecifier,
        SyncAsyncKernel $syncAsync
    ) {
        $this->nodeScopeResolver = $nodeScopeResolver;
        $this->parser = $parser;
        $this->broker = $broker;
        $this->phpDocResolver = $phpDocResolver;
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
                        if ($node instanceof Expr && !isset(self::IGNORED_EXPR_NODES[get_class($node)])) {
                            $type = $scope->getType($node);
                            $node->setAttribute('type', $this->processType($type));
                        }
                    }
                );
            },
            [],
            function () use ($document, $path) {
                $this->broker->setDocument($document);
                $this->parser->setDocument($document);
                $this->phpDocResolver->setDocument($document);
                $this->nodeScopeResolver->setAnalysedFiles([$path]);
            },
            function () {
                $this->broker->setDocument(null);
                $this->parser->setDocument(null);
                $this->phpDocResolver->setDocument(null);
                $this->nodeScopeResolver->setAnalysedFiles([]);
            }
        );

        $deferred->resolve();
    }

    private function processType(PhpStanType $phpStanType): Type
    {
        if ($phpStanType instanceof TypeWithClassName) {
            $type = new ObjectType();
            $type->class = '\\' . ltrim($phpStanType->getClassName(), '\\');

            return $type;
        }

        if ($phpStanType instanceof PhpStanUnionType) {
            $type = new UnionType();
            $type->types = array_map(function (PhpStanType $subtype) {
                return $this->processType($subtype);
            }, $phpStanType->getTypes());

            return $type;
        }

        if ($phpStanType instanceof PhpStanIntersectionType) {
            $type = new IntersectionType();
            $type->types = array_map(function (PhpStanType $subtype) {
                return $this->processType($subtype);
            }, $phpStanType->getTypes());

            return $type;
        }

        $type = new BasicType();
        $type->description = $phpStanType->describe();

        return $type;
    }
}
