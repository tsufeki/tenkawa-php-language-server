<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

interface Template
{
    /**
     * @param array<string,string> $variables
     *
     * @resolve string
     */
    public function render(array $variables): \Generator;
}
