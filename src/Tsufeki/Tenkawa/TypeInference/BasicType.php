<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\TypeInference;

class BasicType implements Type
{
    public $description = 'mixed';

    public function __toString(): string
    {
        return $this->description;
    }
}
