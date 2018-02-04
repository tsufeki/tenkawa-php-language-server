<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PHPStan\Reflection\ClassConstantReflection;
use PHPStan\Reflection\ClassReflection;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;

class IndexClassConstantReflection implements ClassConstantReflection
{
    /**
     * @var ClassReflection
     */
    private $declaringClass;

    /**
     * @var ClassConst
     */
    private $const;

    /**
     * @var mixed
     */
    private $value;

    public function __construct(
        ClassReflection $declaringClass,
        ClassConst $const,
        $value
    ) {
        $this->declaringClass = $declaringClass;
        $this->const = $const;
        $this->value = $value;
    }

    public function getName(): string
    {
        return $this->const->name;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getDeclaringClass(): ClassReflection
    {
        return $this->declaringClass;
    }

    public function isStatic(): bool
    {
        return true;
    }

    public function isPrivate(): bool
    {
        return $this->const->accessibility === ClassLike::M_PRIVATE;
    }

    public function isPublic(): bool
    {
        return $this->const->accessibility === ClassLike::M_PUBLIC;
    }
}
