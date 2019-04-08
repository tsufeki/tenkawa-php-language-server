<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Symfony;

use PHPStan\Rules\Rule;
use PHPStan\Rules\Symfony\ContainerInterfacePrivateServiceRule;
use PHPStan\Rules\Symfony\ContainerInterfaceUnknownServiceRule;
use PHPStan\Symfony\ServiceMap;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\Symfony\RequestDynamicReturnTypeExtension;
use PHPStan\Type\Symfony\ServiceDynamicReturnTypeExtension;
use PHPStan\Type\Symfony\ServiceTypeSpecifyingExtension;
use Tsufeki\HmContainer\Container;
use Tsufeki\HmContainer\Definition\Value;
use Tsufeki\Tenkawa\Php\PhpStan\Analyser\AnalysedProjectAware;
use Tsufeki\Tenkawa\Server\Event\Document\OnProjectOpen;
use Tsufeki\Tenkawa\Server\Event\OnFileChange;
use Tsufeki\Tenkawa\Server\Plugin;
use Tsufeki\Tenkawa\Symfony\Container\ServiceMapUpdater;
use Tsufeki\Tenkawa\Symfony\Container\ServiceMapWatcher;

class SymfonyPlugin extends Plugin
{
    public function configureContainer(Container $container, array $options): void
    {
        $container->setValue(ServiceMap::class, new ServiceMap([]));
        $container->setClass(ServiceMapWatcher::class, null, false, [new Value([
            'var/cache/dev/*DevDebug{Project,}Container.xml',
        ])]);
        $container->setAlias(OnFileChange::class, ServiceMapWatcher::class, true);
        $container->setAlias(OnProjectOpen::class, ServiceMapWatcher::class, true);
        $container->setClass(AnalysedProjectAware::class, ServiceMapUpdater::class, true);

        $container->setClass(DynamicMethodReturnTypeExtension::class, RequestDynamicReturnTypeExtension::class, true);
        $container->setClass(Rule::class, ContainerInterfacePrivateServiceRule::class, true);
        $container->setClass(Rule::class, ContainerInterfaceUnknownServiceRule::class, true);
        $container->setValue('symfony.constant_hassers', true);

        foreach ([
            'Symfony\\Component\\DependencyInjection\\ContainerInterface',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\Controller',
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController',
        ] as $class) {
            $container->setClass(DynamicMethodReturnTypeExtension::class, ServiceDynamicReturnTypeExtension::class, true, [new Value($class), 'symfony.constant_hassers']);
            $container->setClass(MethodTypeSpecifyingExtension::class, ServiceTypeSpecifyingExtension::class, true, [new Value($class)]);
        }
    }
}
