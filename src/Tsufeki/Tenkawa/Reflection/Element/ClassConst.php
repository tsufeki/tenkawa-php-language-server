<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Reflection\Element;

class ClassConst extends Const_
{
    use MemberTrait;

    public function __construct()
    {
        $this->static = true;
    }
}
