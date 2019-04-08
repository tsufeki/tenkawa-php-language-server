<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan\IndexReflection;

use PHPStan\PhpDoc\ResolvedPhpDocBlock;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\FunctionVariantWithPhpDocs;
use PHPStan\Reflection\Native\NativeParameterReflection;
use PHPStan\Reflection\SignatureMap\ParameterSignature;
use PHPStan\Reflection\SignatureMap\SignatureMapProvider;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypehintHelper;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Param;

class SignatureVariantFactory
{
    /**
     * @var SignatureMapProvider
     */
    private $signatureMapProvider;

    public function __construct(SignatureMapProvider $signatureMapProvider)
    {
        $this->signatureMapProvider = $signatureMapProvider;
    }

    public function isCustom(string $functionName): bool
    {
        $functionName = ltrim($functionName, '\\');

        return !$this->signatureMapProvider->hasFunctionSignature($functionName);
    }

    /**
     * @return FunctionVariant[]
     */
    public function getVariants(Function_ $function, ?ResolvedPhpDocBlock $resolvedPhpDoc, ?Type $returnType = null): array
    {
        if ($this->isCustom($this->getSignatureName($function))) {
            return $this->getVariantsFromReflection($function, $resolvedPhpDoc, $returnType);
        }

        return $this->getVariantsFromMap($function);
    }

    /**
     * @return FunctionVariant[]
     */
    private function getVariantsFromReflection(Function_ $function, ?ResolvedPhpDocBlock $resolvedPhpDoc, ?Type $returnType = null): array
    {
        $phpDocParameterTags = [];
        $phpDocReturnTag = null;
        if ($resolvedPhpDoc !== null) {
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

        $returnType = $returnType ?? TypehintHelper::decideTypeFromReflection(
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

        return [
            new FunctionVariantWithPhpDocs(
                $parameters,
                $variadic,
                $returnType,
                $phpDocReturnType,
                $nativeReturnType
            ),
        ];
    }

    /**
     * @return FunctionVariant[]
     */
    private function getVariantsFromMap(Function_ $function): array
    {
        $name = $function->name;
        $className = null;
        if ($function instanceof Method) {
            $className = $function->nameContext->class;
            $name = "$className::$name";
        }
        $name = ltrim($name, '\\');

        $variantName = $name;
        $variants = [];
        $i = 0;
        while ($this->signatureMapProvider->hasFunctionSignature($variantName)) {
            $methodSignature = $this->signatureMapProvider->getFunctionSignature($variantName, $className);
            $variants[] = new FunctionVariant(
                array_map(function (ParameterSignature $parameterSignature): NativeParameterReflection {
                    return new NativeParameterReflection(
                        $parameterSignature->getName(),
                        $parameterSignature->isOptional(),
                        $parameterSignature->getType(),
                        $parameterSignature->passedByReference(),
                        $parameterSignature->isVariadic()
                    );
                }, $methodSignature->getParameters()),
                $methodSignature->isVariadic(),
                $methodSignature->getReturnType()
            );
            $i++;
            $variantName = "$name'$i";
        }

        return $variants;
    }

    private function getSignatureName(Function_ $function): string
    {
        $name = $function->name;
        if ($function instanceof Method) {
            $className = $function->nameContext->class;
            $name = "$className::$name";
        }
        $name = ltrim($name, '\\');

        return $name;
    }
}
