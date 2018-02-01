<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\TypeInference;

class UnionType implements Type
{
    /**
     * @var Type[]
     */
    public $types = [];

    public function __toString(): string
    {
        return implode('|', $this->types);
    }
}
