<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpUnit;

use PHPStan\Rules\PHPUnit\AssertSameBooleanExpectedRule;
use PHPStan\Rules\PHPUnit\AssertSameNullExpectedRule;
use PHPStan\Rules\PHPUnit\AssertSameWithCountRule;
use PHPStan\Rules\Rule;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\FunctionTypeSpecifyingExtension;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\PHPUnit\Assert\AssertFunctionTypeSpecifyingExtension;
use PHPStan\Type\PHPUnit\Assert\AssertMethodTypeSpecifyingExtension;
use PHPStan\Type\PHPUnit\Assert\AssertStaticMethodTypeSpecifyingExtension;
use PHPStan\Type\PHPUnit\CreateMockDynamicReturnTypeExtension;
use PHPStan\Type\PHPUnit\GetMockBuilderDynamicReturnTypeExtension;
use PHPStan\Type\PHPUnit\MockBuilderDynamicReturnTypeExtension;
use PHPStan\Type\StaticMethodTypeSpecifyingExtension;
use Tsufeki\HmContainer\Container;
use Tsufeki\Tenkawa\Server\Plugin;

class PhpUnitPlugin extends Plugin
{
    public function configureContainer(Container $container, array $options): void
    {
        $container->setClass(DynamicMethodReturnTypeExtension::class, CreateMockDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicMethodReturnTypeExtension::class, GetMockBuilderDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicMethodReturnTypeExtension::class, MockBuilderDynamicReturnTypeExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, AssertFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(MethodTypeSpecifyingExtension::class, AssertMethodTypeSpecifyingExtension::class, true);
        $container->setClass(StaticMethodTypeSpecifyingExtension::class, AssertStaticMethodTypeSpecifyingExtension::class, true);

        $container->setClass(Rule::class, AssertSameBooleanExpectedRule::class, true);
        $container->setClass(Rule::class, AssertSameNullExpectedRule::class, true);
        $container->setClass(Rule::class, AssertSameWithCountRule::class, true);
    }
}
