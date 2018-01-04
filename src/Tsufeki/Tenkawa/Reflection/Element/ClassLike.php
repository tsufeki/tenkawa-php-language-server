<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Reflection\Element;

/**
 * Class, interface or trait.
 */
class ClassLike extends Element
{
    const M_PRIVATE = 1;
    const M_PROTECTED = 2;
    const M_PUBLIC = 3;

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
     * @var string|null
     */
    public $parentClass;

    /**
     * @var string[]
     */
    public $interfaces = [];

    /**
     * @var string[]
     */
    public $traits = [];

    /**
     * @var TraitInsteadOf[]
     */
    public $traitInsteadOfs = [];

    /**
     * @var TraitAlias[]
     */
    public $traitAliases = [];
}
