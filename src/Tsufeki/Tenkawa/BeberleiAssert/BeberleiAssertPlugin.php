<?php

namespace Tsufeki\Tenkawa\BeberleiAssert;

use Tsufeki\Tenkawa\Server\Plugin;
use Tsufeki\HmContainer\Container;
use PHPStan\Type\BeberleiAssert\AssertTypeSpecifyingExtension;
use PHPStan\Type\BeberleiAssert\AssertThatFunctionDynamicReturnTypeExtension;
use PHPStan\Type\BeberleiAssert\AssertThatDynamicMethodReturnTypeExtension;
use PHPStan\Type\BeberleiAssert\AssertionChainTypeSpecifyingExtension;
use PHPStan\Type\BeberleiAssert\AssertionChainDynamicReturnTypeExtension;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\StaticMethodTypeSpecifyingExtension;

class BeberleiAssertPlugin extends Plugin
{
    public function configureContainer(Container $container, array $options): void
    {
        $container->setClass(DynamicFunctionReturnTypeExtension::class, AssertThatFunctionDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicMethodReturnTypeExtension::class, AssertionChainDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicStaticMethodReturnTypeExtension::class, AssertThatDynamicMethodReturnTypeExtension::class, true);
        $container->setClass(MethodTypeSpecifyingExtension::class, AssertionChainTypeSpecifyingExtension::class, true);
        $container->setClass(StaticMethodTypeSpecifyingExtension::class, AssertTypeSpecifyingExtension::class, true);
    }
}
