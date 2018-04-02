<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server;

use ReactFilesystemMonitor\FilesystemMonitorFactory;
use ReactFilesystemMonitor\FilesystemMonitorFactoryInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodRegistry;
use Tsufeki\BlancheJsonRpc\Dispatcher\SimpleMethodRegistry;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\HmContainer\Container;
use Tsufeki\KayoJsonMapper\Mapper;
use Tsufeki\KayoJsonMapper\MapperBuilder;
use Tsufeki\KayoJsonMapper\NameMangler\NullNameMangler;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Event\Document\OnChange;
use Tsufeki\Tenkawa\Server\Event\Document\OnClose;
use Tsufeki\Tenkawa\Server\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Server\Event\Document\OnProjectClose;
use Tsufeki\Tenkawa\Server\Event\Document\OnProjectOpen;
use Tsufeki\Tenkawa\Server\Event\EventDispatcher;
use Tsufeki\Tenkawa\Server\Event\OnFileChange;
use Tsufeki\Tenkawa\Server\Event\OnIndexingFinished;
use Tsufeki\Tenkawa\Server\Event\OnInit;
use Tsufeki\Tenkawa\Server\Event\OnShutdown;
use Tsufeki\Tenkawa\Server\Event\OnStart;
use Tsufeki\Tenkawa\Server\Index\FileWatcherHandler;
use Tsufeki\Tenkawa\Server\Index\Index;
use Tsufeki\Tenkawa\Server\Index\Indexer;
use Tsufeki\Tenkawa\Server\Index\IndexStorageFactory;
use Tsufeki\Tenkawa\Server\Index\LocalCacheIndexStorageFactory;
use Tsufeki\Tenkawa\Server\Index\MemoryIndexStorageFactory;
use Tsufeki\Tenkawa\Server\Io\Directories;
use Tsufeki\Tenkawa\Server\Io\FileLister\FileLister;
use Tsufeki\Tenkawa\Server\Io\FileLister\LocalFileLister;
use Tsufeki\Tenkawa\Server\Io\FileReader;
use Tsufeki\Tenkawa\Server\Io\FileWatcher\ClientFileWatcher;
use Tsufeki\Tenkawa\Server\Io\FileWatcher\FileWatcher;
use Tsufeki\Tenkawa\Server\Io\FileWatcher\InotifyWaitFileWatcher;
use Tsufeki\Tenkawa\Server\Io\LocalFileReader;
use Tsufeki\Tenkawa\Server\Language\CodeActionAggregator;
use Tsufeki\Tenkawa\Server\Language\CommandDispatcher;
use Tsufeki\Tenkawa\Server\Language\CompletionAggregator;
use Tsufeki\Tenkawa\Server\Language\DiagnosticsAggregator;
use Tsufeki\Tenkawa\Server\Language\DocumentSymbolsAggregator;
use Tsufeki\Tenkawa\Server\Language\GoToDefinitionAggregator;
use Tsufeki\Tenkawa\Server\Language\HoverAggregator;
use Tsufeki\Tenkawa\Server\Logger\ClientLogger;
use Tsufeki\Tenkawa\Server\Mapper\UriMapper;
use Tsufeki\Tenkawa\Server\Protocol\LanguageClient;

class ServerPlugin extends Plugin
{
    public function configureContainer(Container $container, array $options)
    {
        $container->setValue(EventDispatcher::class, new EventDispatcher($container));
        $container->setClass(ClientLogger::class);

        $container->setClass(OnStart::class, ServerPluginInit::class, true);
        $container->setCallable(Mapper::class, [$this, 'createMapper']);
        $container->setClass(MethodRegistry::class, SimpleMethodRegistry::class);
        $container->setCallable(MappedJsonRpc::class, [MappedJsonRpc::class, 'create']);

        $container->setClass(Directories::class);
        $container->setClass(FileReader::class, LocalFileReader::class);
        $container->setClass(FileLister::class, LocalFileLister::class);

        $container->setClass(MethodProvider::class, Server::class, true);
        $container->setClass(LanguageClient::class, Client::class);
        $container->setClass(DocumentStore::class);

        $container->setClass(DiagnosticsAggregator::class);
        $container->setAlias(OnOpen::class, DiagnosticsAggregator::class, true);
        $container->setAlias(OnChange::class, DiagnosticsAggregator::class, true);
        $container->setAlias(OnIndexingFinished::class, DiagnosticsAggregator::class, true);

        if ($options['index.memory_only'] ?? false) {
            $container->setClass(IndexStorageFactory::class, MemoryIndexStorageFactory::class);
        } else {
            $container->setClass(IndexStorageFactory::class, LocalCacheIndexStorageFactory::class);
        }

        $container->setClass(Indexer::class);
        $container->setAlias(OnStart::class, Indexer::class, true);
        $container->setAlias(OnOpen::class, Indexer::class, true);
        $container->setAlias(OnChange::class, Indexer::class, true);
        $container->setAlias(OnClose::class, Indexer::class, true);
        $container->setAlias(OnProjectOpen::class, Indexer::class, true);
        $container->setAlias(OnFileChange::class, Indexer::class, true);
        $container->setClass(Index::class);

        $container->setClass(ClientFileWatcher::class);
        $container->setClass(InotifyWaitFileWatcher::class);
        $container->setClass(FilesystemMonitorFactoryInterface::class, FilesystemMonitorFactory::class);
        $container->setCallable(FileWatcher::class, [$this, 'createFileWatchers']);
        $container->setClass(FileWatcherHandler::class);
        $container->setAlias(OnInit::class, FileWatcherHandler::class, true);
        $container->setAlias(OnShutdown::class, FileWatcherHandler::class, true);
        $container->setAlias(OnProjectOpen::class, FileWatcherHandler::class, true);
        $container->setAlias(OnProjectClose::class, FileWatcherHandler::class, true);

        $container->setClass(CommandDispatcher::class);
        $container->setClass(HoverAggregator::class);
        $container->setClass(GoToDefinitionAggregator::class);
        $container->setClass(CompletionAggregator::class);
        $container->setClass(DocumentSymbolsAggregator::class);
        $container->setClass(CodeActionAggregator::class);
    }

    /**
     * @internal
     */
    public function createMapper(): Mapper
    {
        $uriMapper = new UriMapper();

        return MapperBuilder::create()
            ->setNameMangler(new NullNameMangler())
            ->setPrivatePropertyAccess(false)
            ->setGuessRequiredProperties(true)
            ->setDumpNullProperties(false)
            ->throwOnMissingProperty(true)
            ->throwOnUnknownProperty(false)
            ->throwOnInfiniteRecursion(true)
            ->acceptStdClassAsArray(true)
            ->setStrictNulls(true)
            ->addLoader($uriMapper)
            ->addDumper($uriMapper)
            ->getMapper();
    }

    public function createFileWatchers(
        ClientFileWatcher $clientFileWatcher,
        InotifyWaitFileWatcher $inotifyWaitFileWatcher
    ): array {
        return [$clientFileWatcher, $inotifyWaitFileWatcher];
    }
}
