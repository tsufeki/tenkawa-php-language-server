<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Index;

use Psr\Log\LoggerInterface;
use Recoil\Recoil;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Event\Document\OnChange;
use Tsufeki\Tenkawa\Server\Event\Document\OnClose;
use Tsufeki\Tenkawa\Server\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Server\Event\Document\OnProjectClose;
use Tsufeki\Tenkawa\Server\Event\Document\OnProjectOpen;
use Tsufeki\Tenkawa\Server\Event\OnStart;
use Tsufeki\Tenkawa\Server\Index\Storage\ChainedStorage;
use Tsufeki\Tenkawa\Server\Index\Storage\MergedStorage;
use Tsufeki\Tenkawa\Server\Index\Storage\WritableIndexStorage;
use Tsufeki\Tenkawa\Server\Io\FileLister\FileFilter;
use Tsufeki\Tenkawa\Server\Io\FileLister\FileLister;
use Tsufeki\Tenkawa\Server\Io\FileReader;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\Stopwatch;

class Indexer implements OnStart, OnOpen, OnChange, OnClose, OnProjectOpen, OnProjectClose
{
    /**
     * @var IndexDataProvider[]
     */
    private $indexDataProviders;

    /**
     * @var GlobalIndexer[]
     */
    private $globalIndexers;

    /**
     * @var IndexStorageFactory
     */
    private $indexStorageFactory;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var FileReader
     */
    private $fileReader;

    /**
     * @var FileLister
     */
    private $fileLister;

    /**
     * @var FileFilter[]
     */
    private $fileFilters;

    /**
     * @var FileFilterFactory[]
     */
    private $fileFilterFactories;

    /**
     * @var WritableIndexStorage
     */
    private $globalIndex;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $indexDataVersion;

    /**
     * @param IndexDataProvider[] $indexDataProviders
     * @param GlobalIndexer[]     $globalIndexers
     * @param FileFilter[]        $fileFilters
     * @param FileFilterFactory[] $fileFilterFactories
     */
    public function __construct(
        array $indexDataProviders,
        array $globalIndexers,
        IndexStorageFactory $indexStorageFactory,
        DocumentStore $documentStore,
        FileReader $fileReader,
        FileLister $fileLister,
        array $fileFilters,
        array $fileFilterFactories,
        LoggerInterface $logger
    ) {
        $this->indexDataProviders = $indexDataProviders;
        $this->globalIndexers = $globalIndexers;
        $this->indexStorageFactory = $indexStorageFactory;
        $this->documentStore = $documentStore;
        $this->fileReader = $fileReader;
        $this->fileLister = $fileLister;
        $this->fileFilters = $fileFilters;
        $this->fileFilterFactories = $fileFilterFactories;
        $this->logger = $logger;

        $versions = array_map(function (IndexDataProvider $provider) {
            return get_class($provider) . '=' . $provider->getVersion();
        }, $this->indexDataProviders);
        sort($versions);
        $this->indexDataVersion = implode(';', $versions);
    }

    private function indexDocument(Document $document, WritableIndexStorage $indexStorage, int $timestamp = null): \Generator
    {
        $entries = [];

        $fileEntry = new IndexEntry();
        $fileEntry->sourceUri = $document->getUri();
        $fileEntry->category = 'file';
        $fileEntry->key = '';
        $entries[] = $fileEntry;

        foreach ($this->indexDataProviders as $provider) {
            $entries = array_merge($entries, yield $provider->getEntries($document));
        }

        yield $indexStorage->replaceFile($document->getUri(), $entries, $timestamp);
    }

    private function clearDocument(Uri $uri, WritableIndexStorage $indexStorage): \Generator
    {
        yield $indexStorage->replaceFile($uri, []);
    }

    public function indexProject(Project $project, WritableIndexStorage $indexStorage): \Generator
    {
        $rootUri = $project->getRootUri();
        if ($rootUri->getScheme() !== 'file' || empty($this->indexDataProviders)) {
            return;
        }

        $fileFilters = array_merge(
            $this->fileFilters,
            yield array_map(function (FileFilterFactory $factory) use ($project) {
                return $factory->getFilter($project);
            }, $this->fileFilterFactories)
        );

        if (empty($fileFilters)) {
            return;
        }

        $stopwatch = new Stopwatch();
        $this->logger->info("Project indexing started: $rootUri");

        $indexedFiles = yield $indexStorage->getFileTimestamps();
        $processedFilesCount = 0;

        foreach (yield $this->fileLister->list($rootUri, $fileFilters) as $uriString => list($language, $timestamp)) {
            yield;
            if (array_key_exists($uriString, $indexedFiles) && $indexedFiles[$uriString] === $timestamp) { // TODO: windows support
                unset($indexedFiles[$uriString]);
                continue;
            }

            try {
                unset($indexedFiles[$uriString]);
                $uri = Uri::fromString($uriString);
                $text = yield $this->fileReader->read($uri);
                $document = yield $this->documentStore->load($uri, $language, $text);
                $processedFilesCount++;

                yield $this->indexDocument($document, $indexStorage, $timestamp);
            } catch (\Throwable $e) {
                $this->logger->warning("Can't index $uriString", ['exception' => $e]);
            }
        }

        foreach ($indexedFiles as $uriString => $timestamp) {
            yield;
            $uri = Uri::fromString($uriString);
            yield $this->clearDocument($uri, $indexStorage);
        }

        $this->logger->info("Project indexing finished: $rootUri [$processedFilesCount files, $stopwatch]");
    }

    public function onStart(array $options): \Generator
    {
        $this->globalIndex = $this->indexStorageFactory->createGlobalIndex($this->indexDataVersion);

        yield Recoil::execute(array_map(function (GlobalIndexer $globalIndexer) {
            return $globalIndexer->index($this->globalIndex, $this);
        }, $this->globalIndexers));
    }

    public function onProjectOpen(Project $project): \Generator
    {
        $openFilesIndex = $this->indexStorageFactory->createOpenedFilesIndex($project, $this->indexDataVersion);
        $projectFilesIndex = $this->indexStorageFactory->createProjectFilesIndex($project, $this->indexDataVersion);

        $index = new ChainedStorage(
            $openFilesIndex,
            new MergedStorage([
                $projectFilesIndex,
                $this->globalIndex,
            ])
        );

        $project->set('index.open_files', $openFilesIndex);
        $project->set('index.project_files', $projectFilesIndex);
        $project->set('index', $index);

        yield Recoil::execute($this->indexProject($project, $projectFilesIndex));
    }

    public function onProjectClose(Project $project): \Generator
    {
        return;
        yield;
    }

    public function onOpen(Document $document): \Generator
    {
        yield $this->onChange($document);
    }

    public function onChange(Document $document): \Generator
    {
        /** @var Project $project */
        $project = yield $this->documentStore->getProjectForDocument($document);

        /** @var WritableIndexStorage $openFilesIndex */
        $openFilesIndex = $project->get('index.open_files');

        yield $this->indexDocument($document, $openFilesIndex, $document->getVersion());
    }

    public function onClose(Document $document): \Generator
    {
        /** @var Project $project */
        $project = yield $this->documentStore->getProjectForDocument($document);

        /** @var WritableIndexStorage $openFilesIndex */
        $openFilesIndex = $project->get('index.open_files');

        yield $this->clearDocument($document->getUri(), $openFilesIndex);
    }
}
