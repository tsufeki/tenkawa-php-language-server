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

    public function __construct(Function_ $function, PhpDocResolver $phpDocResolver)
    {
        $this->function = $function;

        $phpDocParameterTags = [];
        $phpDocReturnTag = null;
        if ($function->docComment) {
            $resolvedPhpDoc = $phpDocResolver->getResolvedPhpDocForReflectionElement($function);
            $phpDocParameterTags = $resolvedPhpDoc->getParamTags();
            $phpDocReturnTag = $resolvedPhpDoc->getReturnTag();
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
