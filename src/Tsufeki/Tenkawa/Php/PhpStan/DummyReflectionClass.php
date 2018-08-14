<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PHPStan\ShouldNotHappenException;

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
        $this->classReflection->getName();
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
        throw new ShouldNotHappenException();
        // TODO
        // \ReflectionProperty
        //   ->getName(): string
        //   ->getDeclaringClass(): \ReflectionClass
        //   ->isPrivate(): bool
        //   ->isProtected(): bool
        //   ->isStatic(): bool
    }
}
