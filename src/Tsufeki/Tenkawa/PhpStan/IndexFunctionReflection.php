<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypehintHelper;
use Tsufeki\Tenkawa\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Reflection\Element\Param;

class IndexFunctionReflection extends FunctionReflection
{
    /**
     * @var Function_
     */
    private $function;

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

    public function __construct(Function_ $function, PhpDocResolver $phpDocResolver)
    {
        $this->function = $function;

        $phpDocParameterTags = [];
        $phpDocReturnTag = null;
        if ($function->docComment) {
            $resolvedPhpDoc = $phpDocResolver->getResolvedPhpDoc($function);
            $phpDocParameterTags = $resolvedPhpDoc->getParamTags();
            $phpDocReturnTag = $resolvedPhpDoc->getReturnTag();
        }

        $this->parameters = array_map(function (Param $param) use ($phpDocParameterTags) {
            return new IndexPhpParameterReflection(
                $param,
                isset($phpDocParameterTags[$param->name]) ? $phpDocParameterTags[$param->name]->getType() : null
            );
        }, $function->params);

        $phpDocReturnType = $phpDocReturnTag !== null ? $phpDocReturnTag->getType() : null;
        $reflectionReturnType = $function->returnType !== null ? new DummyReflectionType($function->returnType->type) : null;
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

        if ($function->callsFuncGetArgs) {
            $this->variadic = true;
        }
        foreach ($function->params as $param) {
            if ($param->variadic) {
                $this->variadic = true;
                break;
            }
        }
    }

    public function getNativeReflection(): \ReflectionFunction
    {
        throw new ShouldNotHappenException();
    }

    public function getName(): string
    {
        return ltrim($this->function->name, '\\');
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
