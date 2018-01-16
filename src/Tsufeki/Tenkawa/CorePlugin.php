<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa;

use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Broker\Broker;
use PHPStan\File\FileHelper;
use PHPStan\Parser\Parser as PhpStanParser;
use PHPStan\PhpDoc\PhpDocNodeResolver;
use PHPStan\PhpDoc\PhpDocStringResolver;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\Type\FileTypeMapper;
use Psr\Log\LoggerInterface;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodRegistry;
use Tsufeki\BlancheJsonRpc\Dispatcher\SimpleMethodRegistry;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\HmContainer\Container;
use Tsufeki\HmContainer\Definition\Value;
use Tsufeki\KayoJsonMapper\Mapper;
use Tsufeki\KayoJsonMapper\MapperBuilder;
use Tsufeki\KayoJsonMapper\NameMangler\NullNameMangler;
use Tsufeki\Tenkawa\Diagnostics\DiagnosticsAggregator;
use Tsufeki\Tenkawa\Diagnostics\DiagnosticsProvider;
use Tsufeki\Tenkawa\Diagnostics\PhplDiagnosticsProvider;
use Tsufeki\Tenkawa\Document\DocumentStore;
use Tsufeki\Tenkawa\Event\Document\OnChange;
use Tsufeki\Tenkawa\Event\Document\OnClose;
use Tsufeki\Tenkawa\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Event\Document\OnProjectClose;
use Tsufeki\Tenkawa\Event\Document\OnProjectOpen;
use Tsufeki\Tenkawa\Event\EventDispatcher;
use Tsufeki\Tenkawa\Event\OnStart;
use Tsufeki\Tenkawa\Index\GlobalIndexer;
use Tsufeki\Tenkawa\Index\Index;
use Tsufeki\Tenkawa\Index\IndexDataProvider;
use Tsufeki\Tenkawa\Index\Indexer;
use Tsufeki\Tenkawa\Index\IndexStorageFactory;
use Tsufeki\Tenkawa\Index\LocalCacheIndexStorageFactory;
use Tsufeki\Tenkawa\Index\MemoryIndexStorageFactory;
use Tsufeki\Tenkawa\Index\StubsIndexer;
use Tsufeki\Tenkawa\Io\Directories;
use Tsufeki\Tenkawa\Io\FileReader;
use Tsufeki\Tenkawa\Io\FileSearch;
use Tsufeki\Tenkawa\Io\LocalFileReader;
use Tsufeki\Tenkawa\Io\LocalFileSearch;
use Tsufeki\Tenkawa\Logger\ClientLogger;
use Tsufeki\Tenkawa\Logger\CompositeLogger;
use Tsufeki\Tenkawa\Logger\StreamLogger;
use Tsufeki\Tenkawa\Mapper\UriMapper;
use Tsufeki\Tenkawa\Parser\Parser;
use Tsufeki\Tenkawa\Parser\ParserDiagnosticsProvider;
use Tsufeki\Tenkawa\Parser\PhpParserAdapter;
use Tsufeki\Tenkawa\PhpStan\DocumentParser;
use Tsufeki\Tenkawa\PhpStan\IndexBroker;
use Tsufeki\Tenkawa\PhpStan\PhpDocResolver;
use Tsufeki\Tenkawa\PhpStan\PhpStanTypeInference;
use Tsufeki\Tenkawa\ProcessRunner\ProcessRunner;
use Tsufeki\Tenkawa\ProcessRunner\ReactProcessRunner;
use Tsufeki\Tenkawa\ProcessRunner\ThrottledProcessRunner;
use Tsufeki\Tenkawa\Protocol\LanguageClient;
use Tsufeki\Tenkawa\References\DocCommentHelper;
use Tsufeki\Tenkawa\References\ExpressionTypeHoverProvider;
use Tsufeki\Tenkawa\References\GlobalsHelper;
use Tsufeki\Tenkawa\References\GoToDefinitionAggregator;
use Tsufeki\Tenkawa\References\GoToDefinitionProvider;
use Tsufeki\Tenkawa\References\GoToDocCommentProvider;
use Tsufeki\Tenkawa\References\GoToGlobalsProvider;
use Tsufeki\Tenkawa\References\HoverAggregator;
use Tsufeki\Tenkawa\References\HoverDocCommentProvider;
use Tsufeki\Tenkawa\References\HoverFormatter;
use Tsufeki\Tenkawa\References\HoverGlobalsProvider;
use Tsufeki\Tenkawa\References\HoverProvider;
use Tsufeki\Tenkawa\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Reflection\IndexReflectionProvider;
use Tsufeki\Tenkawa\Reflection\ReflectionIndexDataProvider;
use Tsufeki\Tenkawa\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\TypeInference\TypeInference;
use Tsufeki\Tenkawa\Utils\Throttler;

class CorePlugin extends Plugin
{
    public function configureContainer(Container $container, array $options)
    {
        $container->setValue(EventDispatcher::class, new EventDispatcher($container));
        $container->setClass(LoggerInterface::class, CompositeLogger::class);
        $container->setClass(ClientLogger::class);
        $container->setClass(StreamLogger::class, null, false, [new Value(STDERR)]);

        $container->setClass(OnStart::class, CorePluginInit::class, true);
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
        $container->setClass(DiagnosticsProvider::class, PhplDiagnosticsProvider::class, true);

        $container->setClass(Parser::class, PhpParserAdapter::class);
        $container->setClass(DiagnosticsProvider::class, ParserDiagnosticsProvider::class, true);

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

        if ($options['index.stubs'] ?? true) {
            $container->setClass(GlobalIndexer::class, StubsIndexer::class, true);
        }

        $container->setClass(IndexDataProvider::class, ReflectionIndexDataProvider::class, true);
        $container->setClass(ReflectionProvider::class, IndexReflectionProvider::class);
        $container->setClass(ClassResolver::class);

        $container->setClass(HoverAggregator::class);
        $container->setClass(HoverFormatter::class);
        $container->setClass(GoToDefinitionAggregator::class);

        $container->setClass(GlobalsHelper::class);
        $container->setClass(GoToDefinitionProvider::class, GoToGlobalsProvider::class, true);
        $container->setClass(HoverProvider::class, HoverGlobalsProvider::class, true);

        $container->setClass(DocCommentHelper::class);
        $container->setClass(GoToDefinitionProvider::class, GoToDocCommentProvider::class, true);
        $container->setClass(HoverProvider::class, HoverDocCommentProvider::class, true);

        $container->setClass(TypeInference::class, PhpStanTypeInference::class);
        $container->setClass(NodeScopeResolver::class, null, false, [null, null, null, null, null, new Value(true), new Value(false), new Value([])]);
        $container->setClass(DocumentParser::class);
        $container->setAlias(PhpStanParser::class, DocumentParser::class);
        $container->setClass(IndexBroker::class);
        $container->setAlias(Broker::class, IndexBroker::class);
        $container->setClass(Standard::class, null, false, [new Value([])]);
        $container->setClass(TypeSpecifier::class);
        $container->setClass(PhpDocResolver::class);
        $container->setAlias(FileTypeMapper::class, PhpDocResolver::class);
        $container->setClass(PhpDocStringResolver::class);
        $container->setClass(Lexer::class);
        $container->setClass(PhpDocParser::class);
        $container->setClass(PhpDocNodeResolver::class);
        $container->setClass(TypeNodeResolver::class);
        $container->setClass(TypeParser::class);
        $container->setClass(ConstExprParser::class);
        $container->setClass(FileHelper::class, null, false, [new Value(getcwd())]);

        $container->setClass(HoverProvider::class, ExpressionTypeHoverProvider::class, true);
    }

    /**
     * @internal
     */
    public function createMapper(): Mapper
    {
        $uriMapper = new UriMapper();

        return MapperBuilder::create()
            ->setNameMangler(new NullNameMangler())
            ->setPrivatePropertyAccess(true)
            ->throwOnInfiniteRecursion(true)
            ->throwOnMissingProperty(false)
            ->throwOnUnknownProperty(false)
            ->acceptStdClassAsArray(true)
            ->addLoader($uriMapper)
            ->addDumper($uriMapper)
            ->getMapper();
    }
}
