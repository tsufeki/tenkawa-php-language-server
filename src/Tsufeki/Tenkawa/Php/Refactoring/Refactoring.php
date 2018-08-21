<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Refactoring;

use PhpParser\Node;

interface Refactoring
{
    /**
     * @param Node[] $nodes
     *
     * @resolve Node[]
     */
    public function refactor(array $nodes): \Generator;
}
