<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\Tenkawa\Php\Reflection\Element\ClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Element\DocComment;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;
use Tsufeki\Tenkawa\Server\Protocol\Common\Location;

class ResolvedClassLike
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
     * @var bool
     */
    public $isClass = false;

    /**
     * @var bool
     */
    public $isInterface = false;

    /**
     * @var bool
     */
    public $isTrait = false;

    /**
     * @var ClassConst[]
     */
    public $consts = [];

    /**
     * @var Property[]
     */
    public $properties = [];

    /**
     * @var Method[]
     */
    public $methods = [];

    /**
     * @var bool
     */
    public $abstract = false;

    /**
     * @var bool
     */
    public $final = false;

    /**
     * @var self|null
     */
    public $parentClass;

    /**
     * @var self[]
     */
    public $interfaces = [];

    /**
     * @var self[]
     */
    public $traits = [];
}
