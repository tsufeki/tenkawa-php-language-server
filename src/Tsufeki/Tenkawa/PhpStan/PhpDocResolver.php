<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

use PHPStan\PhpDoc\ResolvedPhpDocBlock;
use Tsufeki\Tenkawa\Reflection\Element\Element;

class PhpDocResolver
{
    public function getResolvedPhpDoc(Element $element): ResolvedPhpDocBlock
    {
        $comment = $element->docComment;
        $nameContext = $element->nameContext;
    }
}
