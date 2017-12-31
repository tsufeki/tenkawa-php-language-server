<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Reflection\Element;

class TraitAlias
{
    /**
     * @var string
     */
    public $trait;

    /**
     * @var string
     */
    public $method;

    /**
     * @var string|null
     */
    public $newName;

    /**
     * @var int|null
     */
    public $newAccessibility;
}
