<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\TypeInference;

interface Type
{
    public function __toString(): string;
}
