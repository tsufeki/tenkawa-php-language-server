<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\GoToImplementation;

use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\InheritanceTreeVisitor;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassLike;

class FindClassLikeImplementationsVisitor implements InheritanceTreeVisitor
{
    /**
     * @var ResolvedClassLike[]
     */
    private $implementations = [];

    /**
     * @var ClassLike
     */
    private $root;

    public function __construct(ClassLike $root)
    {
        $this->root = $root;
    }

    public function enter(ResolvedClassLike $class): \Generator
    {
        if ($class->location != $this->root->location) {
            $this->implementations[] = $class;
        }

        return;
        yield;
    }

    public function leave(ResolvedClassLike $class): \Generator
    {
        return;
        yield;
    }

    /**
     * @return ResolvedClassLike[]
     */
    public function getImplementations(): array
    {
        // deduplicate
        $result = [];
        foreach ($this->implementations as $impl) {
            foreach ($result as $prevImpl) {
                if ($impl->location == $prevImpl->location) {
                    continue 2;
                }
            }
            $result[] = $impl;
        }

        return $result;
    }
}
