<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\TypeInference;

interface Type
{
    public function __toString(): string;
}
