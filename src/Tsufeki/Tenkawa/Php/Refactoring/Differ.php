<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Refactoring;

use Tsufeki\Tenkawa\Server\Feature\Common\TextEdit;

interface Differ
{
    /**
     * @resolve TextEdit[]
     */
    public function diff(string $from, string $to): \Generator;
}
