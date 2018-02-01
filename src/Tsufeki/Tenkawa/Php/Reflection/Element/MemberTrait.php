<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection\Element;

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
