<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection\Element;

class Method extends Function_
{
    use MemberTrait;

    /**
     * @var bool
     */
    public $abstract = false;

    /**
     * @var bool
     */
    public $final = false;
}
