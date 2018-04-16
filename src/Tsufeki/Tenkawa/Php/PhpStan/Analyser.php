<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\TypeSpecifier;
use Psr\Log\LoggerInterface;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Utils\Cache;
use Tsufeki\Tenkawa\Server\Utils\SyncAsync;

class Analyser
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
     * @var SyncAsync
     */
    private $syncAsync;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        NodeScopeResolver $nodeScopeResolver,
        DocumentParser $parser,
        IndexBroker $broker,
        PhpDocResolver $phpDocResolver,
        Standard $printer,
        TypeSpecifier $typeSpecifier,
        SyncAsync $syncAsync,
        LoggerInterface $logger
    ) {
        $this->nodeScopeResolver = $nodeScopeResolver;
        $this->parser = $parser;
        $this->broker = $broker;
        $this->phpDocResolver = $phpDocResolver;
        $this->printer = $printer;
        $this->typeSpecifier = $typeSpecifier;
        $this->syncAsync = $syncAsync;
        $this->logger = $logger;
    }

    /**
     * @param \Closure $nodeCallback (Node $node, Scope $scope)
     */
    public function analyse(Document $document, \Closure $nodeCallback, Cache $cache = null): \Generator
    {
        if ($document->getLanguage() !== 'php') {
            return;
        }

        $cache = $cache ?? new Cache();
        $path = $document->getUri()->getFilesystemPath();

        $this->syncAsync->callSync(
            function () use ($path, $nodeCallback) {
                try {
                    $this->nodeScopeResolver->processNodes(
                        $this->parser->parseFile($path),
                        new Scope($this->broker, $this->printer, $this->typeSpecifier, $path),
                        $nodeCallback
                    );
                } catch (\Throwable $e) {
                    // These exceptions are thrown when PHPStan chokes on partial AST
                    // with syntax errors or when indexing hasn't caught up.
                    $this->logger->error(
                        'Exception during PHPStan analysis: ' .
                        get_class($e) . ': ' .
                        $e->getMessage() .
                        ' in ' . $e->getFile() . ':' . $e->getLine()
                    );
                    $this->logger->debug('Exception during PHPStan analysis', ['exception' => $e]);
                }
            },
            [],
            function () use ($document, $cache, $path) {
                $this->broker->setDocument($document);
                $this->broker->setCache($cache);
                $this->parser->setDocument($document);
                $this->phpDocResolver->setDocument($document);
                $this->phpDocResolver->setCache($cache);
                $this->nodeScopeResolver->setAnalysedFiles([$path]);
            },
            function () {
                $this->broker->setDocument(null);
                $this->broker->setCache(null);
                $this->parser->setDocument(null);
                $this->phpDocResolver->setDocument(null);
                $this->phpDocResolver->setCache(null);
                $this->nodeScopeResolver->setAnalysedFiles([]);
            }
        );

        $cache->close();

        return;
        yield;
    }
}
