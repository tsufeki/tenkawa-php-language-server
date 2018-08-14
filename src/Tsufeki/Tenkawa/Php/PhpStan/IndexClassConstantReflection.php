<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PHPStan\Reflection\ClassConstantReflection;
use PHPStan\Reflection\ClassReflection;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;

class IndexClassConstantReflection extends ClassConstantReflection
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

    /**
     * @var bool
     */
    private $deprecated = false;

    /**
     * @var bool
     */
    private $internal = false;

    public function __construct(
        ClassReflection $declaringClass,
        ClassConst $const,
        $value,
        PhpDocResolver $phpDocResolver
    ) {
        $this->declaringClass = $declaringClass;
        $this->const = $const;
        $this->value = $value;

        if ($const->docComment) {
            $resolvedPhpDoc = $phpDocResolver->getResolvedPhpDocForReflectionElement($const);
            $this->deprecated = $resolvedPhpDoc->isDeprecated();
            $this->internal = $resolvedPhpDoc->isInternal();
        }
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

    public function isDeprecated(): bool
    {
        return $this->deprecated;
    }

    public function isInternal(): bool
    {
        return $this->internal;
    }
}
