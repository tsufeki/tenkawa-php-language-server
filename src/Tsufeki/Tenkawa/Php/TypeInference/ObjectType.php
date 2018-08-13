<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\TypeInference;

class ObjectType implements Type
{
    public $class;

    public function __construct(?string $class = null)
    {
        $this->class = $class;
    }

    public function __toString(): string
    {
        return $this->class;
    }
}
