<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan\IndexReflection;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\Php\PhpMethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\VoidType;
use Tsufeki\Tenkawa\Php\PhpStan\PhpDocResolver\PhpDocResolver;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;

class IndexMethodReflection extends PhpMethodReflection
{
    /**
     * @var ClassReflection
     */
    private $declaringClass;

    /**
     * @var Method
     */
    private $method;

    /**
     * @var FunctionVariant[]
     */
    private $variants;

    /**
     * @var bool
     */
    private $deprecated = false;

    /**
     * @var bool
     */
    private $internal = false;

    /**
     * @var bool
     */
    private $final = false;

    /**
     * @var Type|null
     */
    private $throwType;

    public function __construct(
        ClassReflection $declaringClass,
        Method $method,
        PhpDocResolver $phpDocResolver,
        SignatureVariantFactory $signatureVariantFactory
    ) {
        $this->declaringClass = $declaringClass;
        $this->method = $method;
        $this->final = $method->final;

        $resolvedPhpDoc = null;
        if ($method->docComment) {
            $resolvedPhpDoc = $phpDocResolver->getResolvedPhpDocForReflectionElement($method);
            $phpDocThrowsTag = $resolvedPhpDoc->getThrowsTag();

            $this->deprecated = $resolvedPhpDoc->isDeprecated();
            $this->internal = $resolvedPhpDoc->isInternal();
            $this->final = $this->final || $resolvedPhpDoc->isFinal();
            $this->throwType = $phpDocThrowsTag ? $phpDocThrowsTag->getType() : null;
        }

        $returnType = null;
        $name = strtolower($this->getName());
        if (
            $name === '__construct'
            || $name === '__destruct'
            || $name === '__unset'
            || $name === '__wakeup'
            || $name === '__clone'
        ) {
            $returnType = new VoidType();
        } elseif ($name === '__tostring') {
            $returnType = new StringType();
        } elseif ($name === '__isset') {
            $returnType = new BooleanType();
        } elseif ($name === '__sleep') {
            $returnType = new ArrayType(new IntegerType(), new StringType());
        } elseif ($name === '__set_state') {
            $returnType = new ObjectWithoutClassType();
        }

        $this->variants = $signatureVariantFactory->getVariants($method, $resolvedPhpDoc, $returnType);
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
        if ($this->method->docComment === null || $this->method->docComment->nameContext !== null) {
            return false;
        }

        return $this->method->docComment->text;
    }

    public function getPrototype(): ClassMemberReflection
    {
        if ($this->isPrivate() || $this->declaringClass->isInterface() || $this->method->abstract) {
            return $this;
        }

        foreach (array_reverse($this->declaringClass->getInterfaces()) as $interface) {
            if ($interface->hasNativeMethod($this->getName())) {
                return $interface->getNativeMethod($this->getName())->getPrototype();
            }
        }

        $parent = $this->declaringClass->getParentClass();
        if (strtolower($this->getName()) !== '__construct' && $parent !== false && $parent->hasNativeMethod($this->getName())) {
            return $parent->getNativeMethod($this->getName())->getPrototype();
        }

        return $this;
    }

    public function isStatic(): bool
    {
        return $this->method->static;
    }

    public function getName(): string
    {
        return $this->method->name;
    }

    /**
     * @return ParametersAcceptor[]
     */
    public function getVariants(): array
    {
        return $this->variants;
    }

    public function isPrivate(): bool
    {
        return $this->method->accessibility === ClassLike::M_PRIVATE;
    }

    public function isPublic(): bool
    {
        return $this->method->accessibility === ClassLike::M_PUBLIC;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecated;
    }

    public function isInternal(): bool
    {
        return $this->internal;
    }

    public function isFinal(): bool
    {
        return $this->final;
    }

    public function getThrowType(): ?Type
    {
        return $this->throwType;
    }

    public function isGenerator(): bool
    {
        return $this->method->isGenerator;
    }
}
