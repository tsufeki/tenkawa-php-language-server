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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param IndexDataProvider[] $indexDataProviders
     */
    public function __construct(
        array $indexDataProviders,
        IndexStorageFactory $indexStorageFactory,
        DocumentStore $documentStore,
        FileReader $fileReader,
        FileSearch $fileSearch,
        LoggerInterface $logger
    ) {
        $this->indexDataProviders = $indexDataProviders;
        $this->indexStorageFactory = $indexStorageFactory;
        $this->documentStore = $documentStore;
        $this->fileReader = $fileReader;
        $this->fileSearch = $fileSearch;
        $this->logger = $logger;

        $this->globs = ['**/*.php' => 'php'];
    }

    private function indexDocument(Document $document, WritableIndexStorage $indexStorage, int $timestamp = null): \Generator
    {
        $entries = [];
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

    private function indexProject(Project $project, WritableIndexStorage $indexStorage): \Generator
    {
        if (empty($this->indexDataProviders) || empty($this->globs)) {
            return;
        }

        $stopwatch = new Stopwatch();
        $rootUri = $project->getRootUri();

        $glob = count($this->globs) === 1 ? array_keys($this->globs)[0] : '{' . implode(',', array_keys($this->globs)) . '}';
        $currentFiles = yield $this->fileSearch->searchWithTimestamps($rootUri, $glob);
        yield;
        $indexedFiles = yield $indexStorage->getFileTimestamps();

        foreach (array_diff_assoc($currentFiles, $indexedFiles) as $uriString => $timestamp) {
            yield;

            $uri = Uri::fromString($uriString);
            $language = $this->getLanguageForFile($project, $uriString);
            $text = yield $this->fileReader->read($uri);
            $document = yield $this->documentStore->load($uri, $language, $text);

            yield $this->indexDocument($document, $indexStorage, $timestamp);
        }

        foreach (array_diff_key($indexedFiles, $currentFiles) as $uriString => $timestamp) {
            $uri = Uri::fromString($uriString);
            yield $this->clearDocument($uri, $indexStorage);
        }

        $this->logger->info("Project indexing finished. [$stopwatch]");
    }

    public function onStart(): \Generator
    {
        $this->globalIndex = $this->indexStorageFactory->createGlobalIndex();
        // TODO
        yield;
    }

    public function onProjectOpen(Project $project): \Generator
    {
        $openFilesIndex = $this->indexStorageFactory->createOpenedFilesIndex($project);
        $projectFilesIndex = $this->indexStorageFactory->createProjectFilesIndex($project);

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
