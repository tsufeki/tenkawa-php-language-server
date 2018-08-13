<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PHPStan\Reflection\Php\PhpParameterReflection;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypehintHelper;
use Tsufeki\Tenkawa\Php\Reflection\Element\Param;

class IndexParameterReflection extends PhpParameterReflection
{
    /**
     * @var Param
     */
    private $param;

    /**
     * @var Type
     */
    private $nativeType;

    /**
     * @var Type
     */
    private $phpDocType;

    /**
     * @var Type
     */
    private $type;

    /**
     * @var string|null
     */
    private $declaringClass;

    public function __construct(Param $param, ?Type $phpDocType, ?string $declaringClass = null)
    {
        $this->param = $param;
        $declaringClass = $declaringClass ? ltrim($declaringClass, '\\') : null;
        if ($phpDocType !== null && $param->defaultNull) {
            $phpDocType = TypeCombinator::addNull($phpDocType);
        }

        $reflectionType = null;
        if ($param->type !== null) {
            $reflectionType = new DummyReflectionType($param->type->type, $param->defaultNull);
        }

        $this->type = TypehintHelper::decideTypeFromReflection(
            $reflectionType,
            $phpDocType,
            $this->declaringClass,
            $this->isVariadic()
        );

        $this->nativeType = TypehintHelper::decideTypeFromReflection(
            $reflectionType,
            null,
            $this->declaringClass,
            $this->isVariadic()
        );

        $this->phpDocType = $phpDocType ?? new MixedType();
        $this->declaringClass = $declaringClass;
    }

    public function isOptional(): bool
    {
        return $this->param->optional;
    }

    public function getName(): string
    {
        return $this->param->name;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function isPassedByReference(): bool
    {
        return $this->param->byRef;
    }

    public function isVariadic(): bool
    {
        return $this->param->variadic;
    }

    public function getPhpDocType(): Type
    {
        return $this->phpDocType;
    }

    public function getNativeType(): Type
    {
        return $this->nativeType;
    }
}
