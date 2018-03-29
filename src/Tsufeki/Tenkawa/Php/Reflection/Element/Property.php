<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection\Element;

class Property extends Variable
{
    use MemberTrait;

    /**
     * @var bool
     */
    public $readable = true;

    /**
     * @var bool
     */
    public $writable = true;
}
