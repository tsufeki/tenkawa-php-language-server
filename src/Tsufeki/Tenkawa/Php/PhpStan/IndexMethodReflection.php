<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariantWithPhpDocs;
use PHPStan\Reflection\ParametersAcceptorWithPhpDocs;
use PHPStan\Reflection\Php\PhpMethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypehintHelper;
use PHPStan\Type\VoidType;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Param;

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
     * @var FunctionVariantWithPhpDocs[]
     */
    private $variants;

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

        /** @var IndexParameterReflection[] $parameters */
        $parameters = array_map(function (Param $param) use ($phpDocParameterTags) {
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
        } else {
            $returnType = TypehintHelper::decideTypeFromReflection(
                $reflectionReturnType,
                $phpDocReturnType
            );
        }

        $nativeReturnType = TypehintHelper::decideTypeFromReflection($reflectionReturnType);
        $phpDocReturnType = $phpDocReturnType ?? new MixedType();

        $variadic = $method->callsFuncGetArgs;
        foreach ($method->params as $param) {
            if ($param->variadic) {
                $variadic = true;
                break;
            }
        }

        $this->variants = [
            new FunctionVariantWithPhpDocs(
                $parameters,
                $variadic,
                $returnType,
                $phpDocReturnType,
                $nativeReturnType
            ),
        ];
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
     * @return ParametersAcceptorWithPhpDocs[]
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
        // TODO
    }

    public function isInternal(): bool
    {
        // TODO
    }

    public function isFinal(): bool
    {
        // TODO
    }

    public function getThrowType(): ?Type
    {
        // TODO
    }
}
