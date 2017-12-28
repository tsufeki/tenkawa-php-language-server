<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa;

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
use Tsufeki\Tenkawa\Diagnostics\ParserDiagnosticsProvider;
use Tsufeki\Tenkawa\Diagnostics\PhplDiagnosticsProvider;
use Tsufeki\Tenkawa\Document\DocumentStore;
use Tsufeki\Tenkawa\Event\Document\OnChange;
use Tsufeki\Tenkawa\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Event\OnStart;
use Tsufeki\Tenkawa\Mapper\UriMapper;
use Tsufeki\Tenkawa\Parser\Parser;
use Tsufeki\Tenkawa\Parser\PhpParserAdapter;
use Tsufeki\Tenkawa\ProcessRunner\ProcessRunner;
use Tsufeki\Tenkawa\ProcessRunner\ReactProcessRunner;
use Tsufeki\Tenkawa\ProcessRunner\ThrottledProcessRunner;
use Tsufeki\Tenkawa\Protocol\LanguageClient;
use Tsufeki\Tenkawa\Utils\Throttler;

class CorePlugin extends Plugin
{
    public function configureContainer(Container $container)
    {
        $container->setClass(OnStart::class, CorePluginInit::class, true);
        $container->setCallable(Mapper::class, [$this, 'createMapper']);
        $container->setClass(MethodRegistry::class, SimpleMethodRegistry::class);
        $container->setCallable(MappedJsonRpc::class, [MappedJsonRpc::class, 'create']);

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
            ->addLoader($uriMapper)
            ->addDumper($uriMapper)
            ->getMapper();
    }
}