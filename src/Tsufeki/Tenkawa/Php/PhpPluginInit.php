<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php;

use PHPStan\Type\TypeCombinator;
use Tsufeki\Tenkawa\Server\Event\OnStart;

class PhpPluginInit implements OnStart
{
    public function onStart(array $options): \Generator
    {
        TypeCombinator::setUnionTypesEnabled(true);

        return;
        yield;
    }
}
