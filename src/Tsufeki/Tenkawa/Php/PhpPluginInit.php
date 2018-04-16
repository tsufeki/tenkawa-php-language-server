<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php;

use PHPStan\Type\TypeCombinator;
use Tsufeki\Tenkawa\Server\Event\OnStart;

class PhpPluginInit implements OnStart
{
    private static $unionTypesSet = false;

    public function onStart(array $options): \Generator
    {
        if (!self::$unionTypesSet) {
            self::$unionTypesSet = true;
            TypeCombinator::setUnionTypesEnabled(true);
        }

        return;
        yield;
    }
}
