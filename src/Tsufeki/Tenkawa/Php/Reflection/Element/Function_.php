<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection\Element;

class Function_ extends Element
{
    /**
     * @var Param[]
     */
    public $params = [];

    /**
     * @var Type|null
     */
    public $returnType;

    /**
     * @var bool
     */
    public $returnByRef = false;

    /**
     * @var bool
     */
    public $callsFuncGetArgs = false;
}
