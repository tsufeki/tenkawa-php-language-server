<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Mockery;

use PHPStan\Mockery\PhpDoc\TypeNodeResolverExtension;
use PHPStan\Mockery\Reflection\StubMethodsClassReflectionExtension;
use PHPStan\Mockery\Type\Allows;
use PHPStan\Mockery\Type\ExpectationAfterStubDynamicReturnTypeExtension;
use PHPStan\Mockery\Type\Expects;
use PHPStan\Mockery\Type\MockDynamicReturnTypeExtension;
use PHPStan\Mockery\Type\ShouldReceiveDynamicReturnTypeExtension;
use PHPStan\Mockery\Type\StubDynamicReturnTypeExtension;
use PHPStan\PhpDoc\TypeNodeResolverExtension as TypeNodeResolverExtensionInterface;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use Tsufeki\HmContainer\Container;
use Tsufeki\HmContainer\Definition\Value;
use Tsufeki\Tenkawa\Server\Plugin;

class MockeryPlugin extends Plugin
{
    public function configureContainer(Container $container, array $options): void
    {
        $container->setClass(MethodsClassReflectionExtension::class, StubMethodsClassReflectionExtension::class, true, [new Value(Allows::class)]);
        $container->setClass(MethodsClassReflectionExtension::class, StubMethodsClassReflectionExtension::class, true, [new Value(Expects::class)]);
        $container->setClass(DynamicMethodReturnTypeExtension::class, StubDynamicReturnTypeExtension::class, true, [new Value(Allows::class), new Value('allows')]);
        $container->setClass(DynamicMethodReturnTypeExtension::class, StubDynamicReturnTypeExtension::class, true, [new Value(Expects::class), new Value('expects')]);
        $container->setClass(DynamicMethodReturnTypeExtension::class, ExpectationAfterStubDynamicReturnTypeExtension::class, true, [new Value(Allows::class)]);
        $container->setClass(DynamicMethodReturnTypeExtension::class, ExpectationAfterStubDynamicReturnTypeExtension::class, true, [new Value(Expects::class)]);
        $container->setClass(DynamicMethodReturnTypeExtension::class, ShouldReceiveDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicStaticMethodReturnTypeExtension::class, MockDynamicReturnTypeExtension::class, true);
        $container->setClass(TypeNodeResolverExtensionInterface::class, TypeNodeResolverExtension::class, true);
    }
}
