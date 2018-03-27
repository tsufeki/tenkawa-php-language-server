<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Server\Index\IndexEntry;

interface ReflectionTransformer
{
    /**
     * @resolve Element
     */
    public function transform(Element $element, IndexEntry $entry): \Generator;
}
