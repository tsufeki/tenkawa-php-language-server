<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa;

use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\BlancheJsonRpc\Transport\Transport;
use Tsufeki\HmContainer\Container;
use Tsufeki\Tenkawa\Event\OnStart;
use Tsufeki\Tenkawa\Transport\RunnableTransport;

class Tenkawa
{
    /**
     * @param Plugin[] $plugins
     */
    public function run(RunnableTransport $transport, array $plugins): \Generator
    {
        $container = new Container();

        foreach ($plugins as $plugin) {
            $plugin->configureContainer($container);
        }

        // Materialize the RPC server
        $container->setValue(Transport::class, $transport);
        $rpc = $container->get(MappedJsonRpc::class);

        yield array_map(function (OnStart $onStart) {
            yield $onStart->onStart();
        }, $container->get(OnStart::class));

        yield $transport->run();
    }
}
