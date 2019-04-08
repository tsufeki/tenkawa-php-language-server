<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan\IndexReflection;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\Php\PhpPropertyReflection;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use Tsufeki\Tenkawa\Php\PhpStan\PhpDocResolver\PhpDocResolver;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;

class IndexPropertyReflection extends PhpPropertyReflection
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
        Property $property,
        PhpDocResolver $phpDocResolver
    ) {
        $this->declaringClass = $declaringClass;
        $this->property = $property;

        $this->type = new MixedType();
        if ($property->docComment) {
            $resolvedPhpDoc = $phpDocResolver->getResolvedPhpDocForReflectionElement($property);
            $phpDocVarTags = $resolvedPhpDoc->getVarTags();
            if (isset($phpDocVarTags[0]) && count($phpDocVarTags) === 1) {
                $this->type = $phpDocVarTags[0]->getType();
            } elseif (isset($phpDocVarTags[$property->name])) {
                $this->type = $phpDocVarTags[$property->name]->getType();
            }

            $this->deprecated = $resolvedPhpDoc->isDeprecated();
            $this->internal = $resolvedPhpDoc->isInternal();
        }
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
        if ($this->property->docComment === null || $this->property->docComment->nameContext !== null) {
            return false;
        }

        return $this->property->docComment->text;
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
        return $this->property->readable;
    }

    public function isWritable(): bool
    {
        return $this->property->writable;
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
