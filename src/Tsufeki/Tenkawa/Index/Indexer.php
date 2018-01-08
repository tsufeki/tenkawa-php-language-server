<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Index;

use Psr\Log\LoggerInterface;
use Recoil\Recoil;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Document\DocumentStore;
use Tsufeki\Tenkawa\Document\Project;
use Tsufeki\Tenkawa\Event\Document\OnChange;
use Tsufeki\Tenkawa\Event\Document\OnClose;
use Tsufeki\Tenkawa\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Event\Document\OnProjectClose;
use Tsufeki\Tenkawa\Event\Document\OnProjectOpen;
use Tsufeki\Tenkawa\Event\OnStart;
use Tsufeki\Tenkawa\Index\Storage\ChainedStorage;
use Tsufeki\Tenkawa\Index\Storage\MergedStorage;
use Tsufeki\Tenkawa\Index\Storage\WritableIndexStorage;
use Tsufeki\Tenkawa\Io\FileReader;
use Tsufeki\Tenkawa\Io\FileSearch;
use Tsufeki\Tenkawa\Uri;
use Tsufeki\Tenkawa\Utils\Stopwatch;
use Webmozart\Glob\Glob;

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
     * @var FileSearch
     */
    private $fileSearch;

    /**
     * @var WritableIndexStorage
     */
    private $globalIndex;

    /**
     * @var array<string,string> Glob => language id, e.g. "**\/*.php" => "php".
     */
    private $globs = [];

    /**
     * @var string[]
     */
    private $blacklistGlobs = [];

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
     */
    public function __construct(
        array $indexDataProviders,
        array $globalIndexers,
        IndexStorageFactory $indexStorageFactory,
        DocumentStore $documentStore,
        FileReader $fileReader,
        FileSearch $fileSearch,
        LoggerInterface $logger
    ) {
        $this->indexDataProviders = $indexDataProviders;
        $this->globalIndexers = $globalIndexers;
        $this->indexStorageFactory = $indexStorageFactory;
        $this->documentStore = $documentStore;
        $this->fileReader = $fileReader;
        $this->fileSearch = $fileSearch;
        $this->logger = $logger;

        $this->globs = ['**/*.php' => 'php'];
        $this->blacklistGlobs = ['var/**/*', 'app/cache/**/*', 'cache/**/*'];

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

    private function getLanguageForFile(Project $project, string $uri): string
    {
        $rootUri = (string)$project->getRootUri();
        foreach ($this->globs as $glob => $language) {
            if (Glob::match($uri, $rootUri . '/' . $glob)) {
                return $language;
            }
        }
    }

    private function joinGlobs(array $globs): string
    {
        return count($globs) === 1 ? $globs[0] : '{' . implode(',', $globs) . '}';
    }

    public function indexProject(Project $project, WritableIndexStorage $indexStorage): \Generator
    {
        if (empty($this->indexDataProviders) || empty($this->globs)) {
            return;
        }

        $this->logger->info('Project indexing started: ' . $project->getRootUri());

        $stopwatch = new Stopwatch();
        $rootUri = $project->getRootUri();

        $glob = $this->joinGlobs(array_keys($this->globs));
        $blacklistGlob = $this->joinGlobs($this->blacklistGlobs);

        $currentFiles = yield $this->fileSearch->searchWithTimestamps($rootUri, $glob, $blacklistGlob);
        yield;
        $indexedFiles = yield $indexStorage->getFileTimestamps();
        $indexedFilesCount = 0;

        foreach (array_diff_assoc($currentFiles, $indexedFiles) as $uriString => $timestamp) {
            yield;

            try {
                $uri = Uri::fromString($uriString);
                $language = $this->getLanguageForFile($project, $uriString);
                $text = yield $this->fileReader->read($uri);
                $document = yield $this->documentStore->load($uri, $language, $text, $project);
                $indexedFilesCount++;

                yield $this->indexDocument($document, $indexStorage, $timestamp);
            } catch (\Throwable $e) {
                $this->logger->warning("Can't index $uriString", ['exception' => $e]);
            }
        }

        foreach (array_diff_key($indexedFiles, $currentFiles) as $uriString => $timestamp) {
            $uri = Uri::fromString($uriString);
            yield $this->clearDocument($uri, $indexStorage);
        }

        $this->logger->info("Project indexing finished. [$indexedFilesCount files, $stopwatch]");
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
        $openFilesIndex = $document->getProject()->get('index.open_files');
        yield $this->indexDocument($document, $openFilesIndex, $document->getVersion());
    }

    public function onClose(Document $document): \Generator
    {
        $openFilesIndex = $document->getProject()->get('index.open_files');
        yield $this->clearDocument($document->getUri(), $openFilesIndex);
    }
}
