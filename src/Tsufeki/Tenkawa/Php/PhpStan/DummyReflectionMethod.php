<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

class DummyReflectionMethod extends \ReflectionMethod
{
    /**
     * @var IndexMethodReflection
     */
    private $methodReflection;

    public function __construct(IndexMethodReflection $methodReflection)
    {
        $this->methodReflection = $methodReflection;
    }

    public function isGenerator()
    {
        return $this->methodReflection->isGenerator();
    }
}
