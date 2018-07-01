<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassLike;

interface InheritanceTreeVisitor
{
    public function enter(ResolvedClassLike $class): \Generator;

    public function leave(ResolvedClassLike $class): \Generator;
}
