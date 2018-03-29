<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection\Element;

use Tsufeki\Tenkawa\Php\Reflection\NameContext;

class DocComment
{
    /**
     * @var string
     */
    public $text;

    /**
     * @var NameContext|null
     */
    public $nameContext;
}
