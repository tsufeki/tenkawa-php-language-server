<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection\Element;

class Param
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var bool
     */
    public $byRef = false;

    /**
     * @var Type|null
     */
    public $type;

    /**
     * @var bool
     */
    public $variadic = false;

    /**
     * @var bool
     */
    public $optional = false;

    /**
     * @var bool
     */
    public $defaultNull = false;
}
