<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\GoToImplementation;

use Tsufeki\Tenkawa\Php\Reflection\InheritanceTreeVisitor;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedMethod;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedProperty;

class FindImplementationsVisitor implements InheritanceTreeVisitor
{
    /**
     * @var (ResolvedMethod|ResolvedProperty|ResolvedClassConst)[]
     */
    private $members;

    /**
     * @var (ResolvedMethod|ResolvedProperty|ResolvedClassConst)[]
     */
    private $implementations = [];

    /**
     * @param (ResolvedMethod|ResolvedProperty|ResolvedClassConst)[] $members
     */
    public function __construct(array $members)
    {
        $this->members = $members;
    }

    public function enter(ResolvedClassLike $class): \Generator
    {
        foreach (array_merge($class->methods, $class->properties, $class->consts) as $childMember) {
            if ($childMember->location !== null) {
                foreach ($childMember->inheritsFrom as $parentMember) {
                    foreach ($this->members as $member) {
                        if ($member->location == $parentMember->location) {
                            $this->implementations[] = $childMember;
                            $this->members[] = $childMember;
                        }
                    }
                }
            }
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
     * @return (ResolvedMethod|ResolvedProperty|ResolvedClassConst)[]
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
