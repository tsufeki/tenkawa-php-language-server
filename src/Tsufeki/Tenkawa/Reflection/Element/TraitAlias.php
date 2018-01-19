<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Reflection\Element;

class TraitAlias
{
    /**
     * @var string|null
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
