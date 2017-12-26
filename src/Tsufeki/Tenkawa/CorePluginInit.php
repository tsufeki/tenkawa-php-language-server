<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa;

use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodRegistry;
use Tsufeki\BlancheJsonRpc\Dispatcher\SimpleMethodRegistry;
use Tsufeki\Tenkawa\Event\OnStart;

class CorePluginInit implements OnStart
{
    /**
     * @var MethodRegistry
     */
    private $methodRegistry;

    /**
     * @var MethodProvider[]
     */
    private $methodProviders;

    /**
     * @param MethodRegistry   $methodRegistry
     * @param MethodProvider[] $methodProviders
     */
    public function __construct(MethodRegistry $methodRegistry, array $methodProviders)
    {
        $this->methodRegistry = $methodRegistry;
        $this->methodProviders = $methodProviders;
    }

    public function onStart(): \Generator
    {
        if ($this->methodRegistry instanceof SimpleMethodRegistry) {
            foreach ($this->methodProviders as $provider) {
                $this->methodRegistry->addProvider($provider);
            }
        }

        return;
        yield;
    }
}
