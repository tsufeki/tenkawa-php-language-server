<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Reflection\Element;

use Tsufeki\Tenkawa\Protocol\Common\Location;

abstract class Element
{
    /**
     * @var string Fully qualified name.
     */
    public $name;

    /**
     * @var Location|null
     */
    public $location;

    /**
     * @var string|null
     */
    public $docComment;
}
