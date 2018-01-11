<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

use PHPStan\Type\Type as InnerType;
use Tsufeki\Tenkawa\TypeInference\Type;

class PhpStanType implements Type
{
    /**
     * @var InnerType
     */
    private $type;

    public function __construct(InnerType $type)
    {
        $this->type = $type;
    }

    public function __toString(): string
    {
        return $this->type->describe();
    }
}
