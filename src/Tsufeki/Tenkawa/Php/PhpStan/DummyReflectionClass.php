<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

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

    public function getName()
    {
        return $this->classReflection->getName();
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

    public function getProperties($filter = null)
    {
        $properties = [];
        foreach ($this->classReflection->getNativeProperties() as $name => $property) {
            $properties[$name] = new DummyReflectionProperty($property, $name);
        }

        return $properties;
    }

    public function hasMethod($name)
    {
        return $this->classReflection->hasNativeMethod($name);
    }

    public function getMethod($name)
    {
        if (!$this->hasMethod($name)) {
            throw new \ReflectionException();
        }

        /** @var IndexMethodReflection $methodReflection */
        $methodReflection = $this->classReflection->getNativeMethod($name);

        return new DummyReflectionMethod($methodReflection);
    }

    public function isUserDefined()
    {
        return $this->classReflection->isUserDefined();
    }
}
