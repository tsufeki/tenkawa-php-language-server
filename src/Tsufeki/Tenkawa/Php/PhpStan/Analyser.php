<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PhpParser\Node;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\ScopeContext;
use PHPStan\Analyser\ScopeFactory;
use Psr\Log\LoggerInterface;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Exception\UriException;
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
     * @var ScopeFactory
     */
    private $scopeFactory;

    /**
     * @var IndexBroker
     */
    private $broker;

    /**
     * @var PhpDocResolver
     */
    private $phpDocResolver;

    /**
     * @var SyncAsync
     */
    private $syncAsync;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var AnalysedDocumentAware[]
     */
    private $documentAware;

    /**
     * @var AnalysedProjectAware[]
     */
    private $projectAware;

    /**
     * @var AnalysedCacheAware[]
     */
    private $cacheAware;

    /**
     * @param AnalysedDocumentAware[] $documentAware
     * @param AnalysedProjectAware[]  $projectAware
     * @param AnalysedCacheAware[]    $cacheAware
     */
    public function __construct(
        NodeScopeResolver $nodeScopeResolver,
        DocumentParser $parser,
        ScopeFactory $scopeFactory,
        IndexBroker $broker,
        PhpDocResolver $phpDocResolver,
        SyncAsync $syncAsync,
        DocumentStore $documentStore,
        LoggerInterface $logger,
        array $documentAware,
        array $projectAware,
        array $cacheAware
    ) {
        $this->nodeScopeResolver = $nodeScopeResolver;
        $this->parser = $parser;
        $this->scopeFactory = $scopeFactory;
        $this->broker = $broker;
        $this->phpDocResolver = $phpDocResolver;
        $this->syncAsync = $syncAsync;
        $this->documentStore = $documentStore;
        $this->logger = $logger;
        $this->documentAware = $documentAware;
        $this->projectAware = $projectAware;
        $this->cacheAware = $cacheAware;
    }

    /**
     * @param \Closure    $nodeCallback (Node $node, Scope $scope)
     * @param Node[]|null $nodes
     */
    public function analyse(
        Document $document,
        \Closure $nodeCallback,
        ?array $nodes = null,
        ?Cache $cache = null
    ): \Generator {
        if ($document->getLanguage() !== 'php') {
            return;
        }

        try {
            $path = $document->getUri()->getFilesystemPath();
        } catch (UriException $e) {
            return;
        }

        $cache = $cache ?? new Cache();
        $project = yield $this->documentStore->getProjectForDocument($document);

        $this->syncAsync->callSync(
            function () use ($path, $nodeCallback, $nodes) {
                try {
                    $this->nodeScopeResolver->processNodes(
                        $nodes ?? $this->parser->parseFile($path),
                        $this->scopeFactory->create(ScopeContext::create($path)),
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
            function () use ($document, $project, $cache, $path) {
                foreach ($this->documentAware as $aware) {
                    $aware->setDocument($document);
                }
                foreach ($this->projectAware as $aware) {
                    $aware->setProject($project);
                }
                foreach ($this->cacheAware as $aware) {
                    $aware->setCache($cache);
                }
                $this->nodeScopeResolver->setAnalysedFiles([$path]);
            },
            function () {
                foreach ($this->documentAware as $aware) {
                    $aware->setDocument(null);
                }
                foreach ($this->projectAware as $aware) {
                    $aware->setProject(null);
                }
                foreach ($this->cacheAware as $aware) {
                    $aware->setCache(null);
                }
                $this->nodeScopeResolver->setAnalysedFiles([]);
            }
        );

        $cache->close();

        return;
        yield;
    }
}
