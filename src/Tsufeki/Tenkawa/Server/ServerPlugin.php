<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server;

use ReactFilesystemMonitor\FilesystemMonitorFactory;
use ReactFilesystemMonitor\FilesystemMonitorFactoryInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodRegistry;
use Tsufeki\BlancheJsonRpc\Dispatcher\SimpleMethodRegistry;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\HmContainer\Container;
use Tsufeki\HmContainer\Definition\Callable_;
use Tsufeki\HmContainer\Definition\Value;
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
use Tsufeki\Tenkawa\Server\Feature\CodeAction\CodeActionFeature;
use Tsufeki\Tenkawa\Server\Feature\Command\CommandFeature;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionFeature;
use Tsufeki\Tenkawa\Server\Feature\Configuration\ConfigurationFeature;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\DiagnosticsFeature;
use Tsufeki\Tenkawa\Server\Feature\DocumentSymbols\DocumentSymbolsFeature;
use Tsufeki\Tenkawa\Server\Feature\Feature;
use Tsufeki\Tenkawa\Server\Feature\FileWatcher\FileWatcherFeature;
use Tsufeki\Tenkawa\Server\Feature\GoToDefinition\GoToDefinitionFeature;
use Tsufeki\Tenkawa\Server\Feature\Hover\HoverFeature;
use Tsufeki\Tenkawa\Server\Feature\LanguageServer;
use Tsufeki\Tenkawa\Server\Feature\Message\MessageFeature;
use Tsufeki\Tenkawa\Server\Feature\ProgressNotification\ProgressNotificationFeature;
use Tsufeki\Tenkawa\Server\Feature\References\ReferencesFeature;
use Tsufeki\Tenkawa\Server\Feature\Registration\RegistrationFeature;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\SignatureHelpFeature;
use Tsufeki\Tenkawa\Server\Feature\TextDocument\TextDocumentFeature;
use Tsufeki\Tenkawa\Server\Feature\Workspace\WorkspaceFeature;
use Tsufeki\Tenkawa\Server\Feature\WorkspaceEdit\WorkspaceEditFeature;
use Tsufeki\Tenkawa\Server\Feature\WorkspaceSymbols\WorkspaceSymbolsFeature;
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
use Tsufeki\Tenkawa\Server\Io\FileWatcher\ClosedDocumentFileWatcher;
use Tsufeki\Tenkawa\Server\Io\FileWatcher\InotifyWaitFileWatcher;
use Tsufeki\Tenkawa\Server\Io\LocalFileReader;
use Tsufeki\Tenkawa\Server\Logger\ClientLogger;
use Tsufeki\Tenkawa\Server\Mapper\UriMapper;
use Tsufeki\Tenkawa\Server\Utils\FuzzyMatcher;

class ServerPlugin extends Plugin
{
    public function configureContainer(Container $container, array $options): void
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

        $container->setClass(DocumentStore::class);

        if ($options['index.memory_only'] ?? false) {
            $container->setClass(IndexStorageFactory::class, MemoryIndexStorageFactory::class);
        } else {
            $container->setClass(IndexStorageFactory::class, LocalCacheIndexStorageFactory::class);
        }

        $container->setClass(Indexer::class);
        $container->setAlias(OnOpen::class, Indexer::class, true);
        $container->setAlias(OnChange::class, Indexer::class, true);
        $container->setAlias(OnClose::class, Indexer::class, true);
        $container->setAlias(OnProjectOpen::class, Indexer::class, true);
        $container->setAlias(OnFileChange::class, Indexer::class, true);
        $container->setClass(Index::class);

        $container->setClass(InotifyWaitFileWatcher::class);
        $container->setClass(ClosedDocumentFileWatcher::class);
        $container->setClass(FilesystemMonitorFactoryInterface::class, FilesystemMonitorFactory::class);
        if ($options['file_watcher'] ?? true) {
            $container->setClass(FileWatcherHandler::class, null, false, [
                new Callable_(function (
                    FileWatcherFeature $clientFileWatcher,
                    InotifyWaitFileWatcher $inotifyWaitFileWatcher
                ) {
                    return [$clientFileWatcher, $inotifyWaitFileWatcher];
                }),
                new Callable_(function (
                    ClosedDocumentFileWatcher $closedDocumentFileWatcher
                ) {
                    return [$closedDocumentFileWatcher];
                }),
                null,
            ]);
        } else {
            $container->setClass(FileWatcherHandler::class, null, false, [
                new Value([]),
                new Value([]),
                null,
            ]);
        }
        $container->setAlias(OnInit::class, FileWatcherHandler::class, true);
        $container->setAlias(OnShutdown::class, FileWatcherHandler::class, true);
        $container->setAlias(OnProjectOpen::class, FileWatcherHandler::class, true);
        $container->setAlias(OnProjectClose::class, FileWatcherHandler::class, true);

        $container->setClass(FuzzyMatcher::class);

        $container->setClass(MethodProvider::class, LanguageServer::class, true);

        $container->setClass(CodeActionFeature::class);
        $container->setAlias(Feature::class, CodeActionFeature::class, true);
        $container->setAlias(MethodProvider::class, CodeActionFeature::class, true);

        $container->setClass(CommandFeature::class);
        $container->setAlias(Feature::class, CommandFeature::class, true);
        $container->setAlias(MethodProvider::class, CommandFeature::class, true);

        $container->setClass(CompletionFeature::class);
        $container->setAlias(Feature::class, CompletionFeature::class, true);
        $container->setAlias(MethodProvider::class, CompletionFeature::class, true);

        $container->setClass(ConfigurationFeature::class);
        $container->setAlias(Feature::class, ConfigurationFeature::class, true);
        $container->setAlias(MethodProvider::class, ConfigurationFeature::class, true);
        $container->setAlias(OnInit::class, ConfigurationFeature::class, true);

        $container->setClass(DiagnosticsFeature::class);
        $container->setAlias(Feature::class, DiagnosticsFeature::class, true);
        $container->setAlias(OnOpen::class, DiagnosticsFeature::class, true);
        $container->setAlias(OnChange::class, DiagnosticsFeature::class, true);
        $container->setAlias(OnIndexingFinished::class, DiagnosticsFeature::class, true);

        $container->setClass(DocumentSymbolsFeature::class);
        $container->setAlias(Feature::class, DocumentSymbolsFeature::class, true);
        $container->setAlias(MethodProvider::class, DocumentSymbolsFeature::class, true);

        $container->setClass(FileWatcherFeature::class);
        $container->setAlias(Feature::class, FileWatcherFeature::class, true);
        $container->setAlias(MethodProvider::class, FileWatcherFeature::class, true);

        $container->setClass(GoToDefinitionFeature::class);
        $container->setAlias(Feature::class, GoToDefinitionFeature::class, true);
        $container->setAlias(MethodProvider::class, GoToDefinitionFeature::class, true);

        $container->setClass(HoverFeature::class);
        $container->setAlias(Feature::class, HoverFeature::class, true);
        $container->setAlias(MethodProvider::class, HoverFeature::class, true);

        $container->setClass(MessageFeature::class);
        $container->setAlias(Feature::class, MessageFeature::class, true);

        $container->setClass(ProgressNotificationFeature::class);
        $container->setAlias(Feature::class, ProgressNotificationFeature::class, true);

        $container->setClass(ReferencesFeature::class);
        $container->setAlias(Feature::class, ReferencesFeature::class, true);
        $container->setAlias(MethodProvider::class, ReferencesFeature::class, true);

        $container->setClass(RegistrationFeature::class);
        $container->setAlias(Feature::class, RegistrationFeature::class, true);

        $container->setClass(SignatureHelpFeature::class);
        $container->setAlias(Feature::class, SignatureHelpFeature::class, true);
        $container->setAlias(MethodProvider::class, SignatureHelpFeature::class, true);

        $container->setClass(TextDocumentFeature::class);
        $container->setAlias(Feature::class, TextDocumentFeature::class, true);
        $container->setAlias(MethodProvider::class, TextDocumentFeature::class, true);

        $container->setClass(WorkspaceFeature::class);
        $container->setAlias(Feature::class, WorkspaceFeature::class, true);
        $container->setAlias(MethodProvider::class, WorkspaceFeature::class, true);

        $container->setClass(WorkspaceEditFeature::class);
        $container->setAlias(Feature::class, WorkspaceEditFeature::class, true);

        $container->setClass(WorkspaceSymbolsFeature::class);
        $container->setAlias(Feature::class, WorkspaceSymbolsFeature::class, true);
        $container->setAlias(MethodProvider::class, WorkspaceSymbolsFeature::class, true);
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
            ->setConvertFloatToInt(true)
            ->addLoader($uriMapper)
            ->addDumper($uriMapper)
            ->getMapper();
    }
}
