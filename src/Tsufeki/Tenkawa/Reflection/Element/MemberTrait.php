<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Reflection\Element;

trait MemberTrait
{
    /**
     * @var int
     */
    public $accessibility = ClassLike::M_PUBLIC;

    /**
     * @var bool
     */
    public $static = false;
}
