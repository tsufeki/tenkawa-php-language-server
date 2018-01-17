<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\TypeInference;

class ObjectType implements Type
{
    public $class;

    public function __toString(): string
    {
        return $this->class;
    }
}
