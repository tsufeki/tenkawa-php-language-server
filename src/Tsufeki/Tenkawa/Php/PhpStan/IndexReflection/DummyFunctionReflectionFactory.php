<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan\IndexReflection;

use PHPStan\Reflection\FunctionReflectionFactory;
use PHPStan\Reflection\Php\PhpFunctionReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Type;

class DummyFunctionReflectionFactory implements FunctionReflectionFactory
{
    public function create(
        \ReflectionFunction $reflection,
        array $phpDocParameterTypes,
        ?Type $phpDocReturnType,
        ?Type $phpDocThrowType,
        bool $isDeprecated,
        bool $isInternal,
        bool $isFinal,
        $filename
    ): PhpFunctionReflection {
        throw new ShouldNotHappenException();
    }
}
