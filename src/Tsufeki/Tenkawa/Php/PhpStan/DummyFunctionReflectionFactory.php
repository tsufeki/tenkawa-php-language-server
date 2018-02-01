<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\FunctionReflectionFactory;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Type;

class DummyFunctionReflectionFactory implements FunctionReflectionFactory
{
    public function create(
        \ReflectionFunction $reflection,
        array $phpDocParameterTypes,
        Type $phpDocReturnType = null
    ): FunctionReflection {
        throw new ShouldNotHappenException();
    }
}
