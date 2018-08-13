<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PHPStan\Reflection\FunctionVariantWithPhpDocs;
use PHPStan\Reflection\ParametersAcceptorWithPhpDocs;
use PHPStan\Reflection\Php\PhpFunctionReflection;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypehintHelper;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Param;

class IndexFunctionReflection extends PhpFunctionReflection
{
    /**
     * @var Function_
     */
    private $function;

    /**
     * @var FunctionVariantWithPhpDocs[]
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

    public function __construct(Function_ $function, PhpDocResolver $phpDocResolver)
    {
        $this->function = $function;

        $phpDocParameterTags = [];
        $phpDocReturnTag = null;
        if ($function->docComment) {
            $resolvedPhpDoc = $phpDocResolver->getResolvedPhpDocForReflectionElement($function);
            $phpDocParameterTags = $resolvedPhpDoc->getParamTags();
            $phpDocReturnTag = $resolvedPhpDoc->getReturnTag();
            $phpDocThrowsTag = $resolvedPhpDoc->getThrowsTag();

            $this->deprecated = $resolvedPhpDoc->isDeprecated();
            $this->internal = $resolvedPhpDoc->isInternal();
            $this->final = $resolvedPhpDoc->isFinal();
            $this->throwType = $phpDocThrowsTag ? $phpDocThrowsTag->getType() : null;
        }

        /** @var IndexParameterReflection[] $parameters */
        $parameters = array_map(function (Param $param) use ($phpDocParameterTags) {
            return new IndexParameterReflection(
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

        $returnType = TypehintHelper::decideTypeFromReflection(
            $reflectionReturnType,
            $phpDocReturnType
        );

        $nativeReturnType = TypehintHelper::decideTypeFromReflection($reflectionReturnType);
        $phpDocReturnType = $phpDocReturnType ?? new MixedType();

        $variadic = $function->callsFuncGetArgs;
        foreach ($function->params as $param) {
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

    public function getName(): string
    {
        return ltrim($this->function->name, '\\');
    }

    /**
     * @return ParametersAcceptorWithPhpDocs[]
     */
    public function getVariants(): array
    {
        return $this->variants;
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
}
