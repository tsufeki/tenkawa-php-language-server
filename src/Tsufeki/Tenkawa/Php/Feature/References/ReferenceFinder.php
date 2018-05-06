<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\References;

use Tsufeki\Tenkawa\Php\Feature\Symbol;

interface ReferenceFinder
{
    /**
     * @resolve Reference[]
     */
    public function getReferences(Symbol $symbol, bool $includeDeclaration = false): \Generator;
}
