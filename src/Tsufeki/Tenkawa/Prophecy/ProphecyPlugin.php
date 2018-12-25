<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Prophecy;

use JanGregor\Prophecy\Extension\ObjectProphecyRevealDynamicReturnTypeExtension;
use JanGregor\Prophecy\Extension\ProphetProphesizeDynamicReturnTypeExtension;
use JanGregor\Prophecy\Extension\TestCaseProphesizeDynamicReturnTypeExtension;
use JanGregor\Prophecy\Reflection\ProphecyMethodsClassReflectionExtension;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use Tsufeki\HmContainer\Container;
use Tsufeki\Tenkawa\Server\Plugin;

class ProphecyPlugin extends Plugin
{
    public function configureContainer(Container $container, array $options): void
    {
        $container->setClass(DynamicMethodReturnTypeExtension::class, ObjectProphecyRevealDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicMethodReturnTypeExtension::class, TestCaseProphesizeDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicMethodReturnTypeExtension::class, ProphetProphesizeDynamicReturnTypeExtension::class, true);
        $container->setClass(MethodsClassReflectionExtension::class, ProphecyMethodsClassReflectionExtension::class, true);
    }
}
