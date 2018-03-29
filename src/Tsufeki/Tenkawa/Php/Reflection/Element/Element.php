<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection\Element;

use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Server\Protocol\Common\Location;

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
     * @var DocComment|null
     */
    public $docComment;

    /**
     * @var NameContext
     */
    public $nameContext;

    /**
     * @var string|null
     */
    public $origin;
}
