<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection\Resolved;

trait ResolvedMemberTrait
{
    /**
     * @var (ResolvedClassConst|ResolvedProperty|ResolvedMethod)[]
     */
    public $inheritsFrom = [];
}
