<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

class DummyReflectionClass extends \ReflectionClass
{
    /**
     * @var IndexClassReflection
     */
    private $classReflection;

    public function __construct(IndexClassReflection $classReflection)
    {
        $this->classReflection = $classReflection;
    }

    public function getInterfaceNames()
    {
        return $this->classReflection->getInterfaceNames();
    }

    public function isFinal()
    {
        return $this->classReflection->isFinal();
    }

    public function implementsInterface($interface)
    {
        $interface = strtolower($interface);

        if ($this->classReflection->isInterface() && strtolower($this->classReflection->getName()) === $interface) {
            return true;
        }

        foreach ($this->classReflection->getInterfaceNames() as $iface) {
            if (strtolower($iface) === $interface) {
                return true;
            }
        }

        return false;
    }
}
