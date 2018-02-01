<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server;

use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodRegistry;
use Tsufeki\BlancheJsonRpc\Dispatcher\SimpleMethodRegistry;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\HmContainer\Container;
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
use Tsufeki\Tenkawa\Server\Event\OnStart;
use Tsufeki\Tenkawa\Server\Index\Index;
use Tsufeki\Tenkawa\Server\Index\Indexer;
use Tsufeki\Tenkawa\Server\Index\IndexStorageFactory;
use Tsufeki\Tenkawa\Server\Index\LocalCacheIndexStorageFactory;
use Tsufeki\Tenkawa\Server\Index\MemoryIndexStorageFactory;
use Tsufeki\Tenkawa\Server\Io\Directories;
use Tsufeki\Tenkawa\Server\Io\FileReader;
use Tsufeki\Tenkawa\Server\Io\FileSearch;
use Tsufeki\Tenkawa\Server\Io\LocalFileReader;
use Tsufeki\Tenkawa\Server\Io\LocalFileSearch;
use Tsufeki\Tenkawa\Server\Language\CompletionAggregator;
use Tsufeki\Tenkawa\Server\Language\DiagnosticsAggregator;
use Tsufeki\Tenkawa\Server\Language\DocumentSymbolsAggregator;
use Tsufeki\Tenkawa\Server\Language\GoToDefinitionAggregator;
use Tsufeki\Tenkawa\Server\Language\HoverAggregator;
use Tsufeki\Tenkawa\Server\Logger\ClientLogger;
use Tsufeki\Tenkawa\Server\Mapper\UriMapper;
use Tsufeki\Tenkawa\Server\ProcessRunner\ProcessRunner;
use Tsufeki\Tenkawa\Server\ProcessRunner\ReactProcessRunner;
use Tsufeki\Tenkawa\Server\ProcessRunner\ThrottledProcessRunner;
use Tsufeki\Tenkawa\Server\Protocol\LanguageClient;
use Tsufeki\Tenkawa\Server\Utils\Throttler;

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
        $container->setClass(FileSearch::class, LocalFileSearch::class);

        $container->setClass(ReactProcessRunner::class);
        $container->setClass(ProcessRunner::class, ThrottledProcessRunner::class, false, [
            ReactProcessRunner::class,
            new Value(new Throttler(8)),
        ]);

        $container->setClass(MethodProvider::class, Server::class, true);
        $container->setClass(LanguageClient::class, Client::class);
        $container->setClass(DocumentStore::class);

        $container->setClass(DiagnosticsAggregator::class);
        $container->setAlias(OnOpen::class, DiagnosticsAggregator::class, true);
        $container->setAlias(OnChange::class, DiagnosticsAggregator::class, true);

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
        $container->setAlias(OnProjectClose::class, Indexer::class, true);
        $container->setClass(Index::class);

        $container->setClass(HoverAggregator::class);
        $container->setClass(GoToDefinitionAggregator::class);
        $container->setClass(CompletionAggregator::class);
        $container->setClass(DocumentSymbolsAggregator::class);
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
}
