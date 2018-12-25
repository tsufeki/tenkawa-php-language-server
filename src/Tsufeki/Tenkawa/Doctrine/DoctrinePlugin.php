<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Doctrine;

use PHPStan\Reflection\Doctrine\DoctrineSelectableClassReflectionExtension;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\Doctrine\DoctrineSelectableDynamicReturnTypeExtension;
use PHPStan\Type\Doctrine\EntityManagerFindDynamicReturnTypeExtension;
use PHPStan\Type\Doctrine\EntityManagerGetRepositoryDynamicReturnTypeExtension;
use PHPStan\Type\Doctrine\EntityRepositoryDynamicReturnTypeExtension;
use PHPStan\Type\Doctrine\ObjectManagerMergeDynamicReturnTypeExtension;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use Tsufeki\HmContainer\Container;
use Tsufeki\HmContainer\Definition\Value;
use Tsufeki\Tenkawa\Server\Plugin;

class DoctrinePlugin extends Plugin
{
    public function configureContainer(Container $container, array $options): void
    {
        $container->setClass(DynamicMethodReturnTypeExtension::class, DoctrineSelectableDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicMethodReturnTypeExtension::class, EntityManagerFindDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicMethodReturnTypeExtension::class, EntityManagerGetRepositoryDynamicReturnTypeExtension::class, true, [new Value('Doctrine\ORM\EntityRepository')]);
        $container->setClass(DynamicMethodReturnTypeExtension::class, EntityRepositoryDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicMethodReturnTypeExtension::class, ObjectManagerMergeDynamicReturnTypeExtension::class, true);
        $container->setClass(MethodsClassReflectionExtension::class, DoctrineSelectableClassReflectionExtension::class, true);
    }
}
