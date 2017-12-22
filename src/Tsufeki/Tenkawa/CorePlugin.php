<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa;

use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\HmContainer\Container;
use Tsufeki\KayoJsonMapper\Mapper;
use Tsufeki\KayoJsonMapper\MapperBuilder;
use Tsufeki\KayoJsonMapper\NameMangler\NullNameMangler;

class CorePlugin extends Plugin
{
    public function configureContainer(Container $container)
    {
        $container->setCallable(Mapper::class, function () {
            return MapperBuilder::create()
                ->setNameMangler(new NullNameMangler())
                ->setPrivatePropertyAccess(true)
                ->throwOnInfiniteRecursion(true)
                ->throwOnMissingProperty(false)
                ->throwOnUnknownProperty(false)
                ->getMapper();
        });

        $container->setCallable(MappedJsonRpc::class, [MappedJsonRpc::class, 'create']);
    }
}
