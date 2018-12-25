<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\WebMozartAssert;

use PHPStan\Type\StaticMethodTypeSpecifyingExtension;
use PHPStan\Type\WebMozartAssert\AssertTypeSpecifyingExtension;
use Tsufeki\HmContainer\Container;
use Tsufeki\Tenkawa\Server\Plugin;

class WebMozartAssertPlugin extends Plugin
{
    public function configureContainer(Container $container, array $options): void
    {
        $container->setClass(AssertTypeSpecifyingExtension::class, StaticMethodTypeSpecifyingExtension::class, true);
    }
}
