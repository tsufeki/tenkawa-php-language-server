<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Type\Type;
use Tsufeki\Tenkawa\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Reflection\Element\Property;

class IndexPhpPropertyReflection implements PropertyReflection
{
    /**
     * @var ClassReflection
     */
    private $declaringClass;

    /**
     * @var Property
     */
    private $property;

    /**
     * @var Type
     */
    private $type;

    public function __construct(
        ClassReflection $declaringClass,
        Property $property,
        Type $type
    ) {
        $this->declaringClass = $declaringClass;
        $this->property = $property;
        $this->type = $type;
    }

    public function getDeclaringClass(): ClassReflection
    {
        return $this->declaringClass;
    }

    /**
     * @return string|false
     */
    public function getDocComment()
    {
        return $this->property->docComment ?? false;
    }

    public function isStatic(): bool
    {
        return $this->property->static;
    }

    public function isPrivate(): bool
    {
        return $this->property->accessibility === ClassLike::M_PRIVATE;
    }

    public function isPublic(): bool
    {
        return $this->property->accessibility === ClassLike::M_PUBLIC;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function isWritable(): bool
    {
        return true;
    }
}
