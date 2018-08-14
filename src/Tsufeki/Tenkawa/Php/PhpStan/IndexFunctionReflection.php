<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\Reflection\Php\PhpFunctionReflection;
use PHPStan\Type\Type;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;

class IndexFunctionReflection extends PhpFunctionReflection
{
    /**
     * @var Function_
     */
    private $function;

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

    public function __construct(Function_ $function, PhpDocResolver $phpDocResolver, SignatureVariantFactory $signatureVariantFactory)
    {
        $this->function = $function;

        $resolvedPhpDoc = null;
        if ($function->docComment) {
            $resolvedPhpDoc = $phpDocResolver->getResolvedPhpDocForReflectionElement($function);
            $phpDocThrowsTag = $resolvedPhpDoc->getThrowsTag();

            $this->deprecated = $resolvedPhpDoc->isDeprecated();
            $this->internal = $resolvedPhpDoc->isInternal();
            $this->final = $resolvedPhpDoc->isFinal();
            $this->throwType = $phpDocThrowsTag ? $phpDocThrowsTag->getType() : null;
        }

        $this->variants = $signatureVariantFactory->getVariants($function, $resolvedPhpDoc);
    }

    public function getName(): string
    {
        return ltrim($this->function->name, '\\');
    }

    /**
     * @return ParametersAcceptor[]
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
