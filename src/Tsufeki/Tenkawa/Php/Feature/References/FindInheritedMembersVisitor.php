<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\References;

use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\InheritanceTreeVisitor;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedMethod;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedProperty;

class FindInheritedMembersVisitor implements InheritanceTreeVisitor
{
    /**
     * @var array<string,string[]> member name => class names
     */
    private $members = [];

    /**
     * @var Element[][]
     */
    private $memberStack = [];

    /**
     * @param Element[] $members
     */
    public function __construct(array $members)
    {
        $this->memberStack[] = $members;
    }

    public function enter(ResolvedClassLike $class): \Generator
    {
        $parentMembers = $this->memberStack[count($this->memberStack) - 1];
        $allMembers = array_merge($class->properties, $class->methods, $class->consts);
        $members = [];

        /** @var ResolvedMethod|ResolvedProperty|ResolvedClassConst $member */
        foreach ($allMembers as $member) {
            /** @var ResolvedMethod|ResolvedProperty|ResolvedClassConst $parentMember */
            foreach ($parentMembers as $parentMember) {
                if ($this->isInheritedFrom($member, $parentMember)) {
                    $members[] = $member;
                    $this->members[$member->name][] = $class->name;
                }
            }
        }

        $this->memberStack[] = $members;

        return;
        yield;
    }

    public function leave(ResolvedClassLike $class): \Generator
    {
        array_pop($this->memberStack);

        return;
        yield;
    }

    /**
     * @return array<string,string[]> member name => class names
     */
    public function getInheritedMembers(): array
    {
        return $this->members;
    }

    /**
     * @param ResolvedMethod|ResolvedProperty|ResolvedClassConst $member
     * @param ResolvedMethod|ResolvedProperty|ResolvedClassConst $parentMember
     */
    private function isInheritedFrom(Element $member, Element $parentMember): bool
    {
        if ($member->location == $parentMember->location) {
            return true;
        }

        foreach ($member->inheritsFrom as $parentCandidate) {
            if ($parentCandidate->location == $parentMember->location) {
                return true;
            }
        }

        return false;
    }
}
