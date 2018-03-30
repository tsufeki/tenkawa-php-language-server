<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\Tenkawa\Php\Reflection\Element\Element;

interface ReflectionTransformer
{
    /**
     * @resolve Element
     */
    public function transform(Element $element): \Generator;
}
