<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection\Element;

class TraitInsteadOf
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
     * Fully qualified names of overriden traits.
     *
     * @var string[]
     */
    public $insteadOfs = [];
}
