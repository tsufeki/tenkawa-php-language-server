<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Phony;

use Eloquent\Phpstan\Phony\Type\HandleProperties;
use Eloquent\Phpstan\Phony\Type\InstanceHandleGetReturnType;
use Eloquent\Phpstan\Phony\Type\MockBuilderGetReturnType;
use Eloquent\Phpstan\Phony\Type\MockBuilderReturnType;
use Eloquent\Phpstan\Phony\Type\MockReturnType;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use Tsufeki\HmContainer\Container;
use Tsufeki\HmContainer\Definition\Value;
use Tsufeki\Tenkawa\Server\Plugin;

class PhonyPlugin extends Plugin
{
    public function configureContainer(Container $container, array $options): void
    {
        $container->setClass(DynamicMethodReturnTypeExtension::class, InstanceHandleGetReturnType::class, true);
        $container->setClass(DynamicMethodReturnTypeExtension::class, MockBuilderGetReturnType::class, true);
        $container->setClass(PropertiesClassReflectionExtension::class, HandleProperties::class, true);

        foreach ([
            'Eloquent\Phony',
            'Eloquent\Phony\Kahlan',
            'Eloquent\Phony\Phpunit',
            'Eloquent\Phony\Pho',
        ] as $namespace) {
            $container->setClass(MockBuilderReturnType::class, null, false, [new Value($namespace)]);
            $container->setAlias(DynamicFunctionReturnTypeExtension::class, MockBuilderReturnType::class, true);
            $container->setAlias(DynamicStaticMethodReturnTypeExtension::class, MockBuilderReturnType::class, true);

            $container->setClass(MockReturnType::class, null, false, [new Value($namespace)]);
            $container->setAlias(DynamicFunctionReturnTypeExtension::class, MockReturnType::class, true);
            $container->setAlias(DynamicStaticMethodReturnTypeExtension::class, MockReturnType::class, true);
        }
    }
}
