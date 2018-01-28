<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\Php\PhpMethodReflection;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypehintHelper;
use Tsufeki\Tenkawa\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Reflection\Element\Method;
use Tsufeki\Tenkawa\Reflection\Element\Param;

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
     * @var ParameterReflection[]
     */
    private $parameters;

    /**
     * @var Type
     */
    private $nativeReturnType;

    /**
     * @var Type
     */
    private $phpDocReturnType;

    /**
     * @var Type
     */
    private $returnType;

    /**
     * @var bool
     */
    private $variadic = false;

    public function __construct(
        ClassReflection $declaringClass,
        Method $method,
        PhpDocResolver $phpDocResolver
    ) {
        $this->declaringClass = $declaringClass;
        $this->method = $method;

        $phpDocParameterTags = [];
        $phpDocReturnTag = null;
        if ($method->docComment) {
            $resolvedPhpDoc = $phpDocResolver->getResolvedPhpDocForReflectionElement($method);
            $phpDocParameterTags = $resolvedPhpDoc->getParamTags();
            $phpDocReturnTag = $resolvedPhpDoc->getReturnTag();
        }

        $this->parameters = array_map(function (Param $param) use ($phpDocParameterTags) {
            return new IndexParameterReflection(
                $param,
                isset($phpDocParameterTags[$param->name]) ? $phpDocParameterTags[$param->name]->getType() : null
            );
        }, $method->params);

        $phpDocReturnType = $phpDocReturnTag !== null ? $phpDocReturnTag->getType() : null;
        $reflectionReturnType = $method->returnType !== null ? new DummyReflectionType($method->returnType->type) : null;
        if (
            $reflectionReturnType !== null
            && $phpDocReturnType !== null
            && $reflectionReturnType->allowsNull() !== TypeCombinator::containsNull($phpDocReturnType)
        ) {
            $phpDocReturnType = null;
        }

        $this->returnType = TypehintHelper::decideTypeFromReflection(
            $reflectionReturnType,
            $phpDocReturnType
        );

        $this->nativeReturnType = TypehintHelper::decideTypeFromReflection($reflectionReturnType);
        $this->phpDocReturnType = $phpDocReturnType ?? new MixedType();

        if ($method->callsFuncGetArgs) {
            $this->variadic = true;
        }
        foreach ($method->params as $param) {
            if ($param->variadic) {
                $this->variadic = true;
                break;
            }
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
        return $this->method->docComment ?? false;
    }

    public function getPrototype(): MethodReflection
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
     * @return ParameterReflection[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function isVariadic(): bool
    {
        return $this->variadic;
    }

    public function isPrivate(): bool
    {
        return $this->method->accessibility === ClassLike::M_PRIVATE;
    }

    public function isPublic(): bool
    {
        return $this->method->accessibility === ClassLike::M_PUBLIC;
    }

    public function getReturnType(): Type
    {
        return $this->returnType;
    }

    public function getPhpDocReturnType(): Type
    {
        return $this->phpDocReturnType;
    }

    public function getNativeReturnType(): Type
    {
        return $this->nativeReturnType;
    }
}
