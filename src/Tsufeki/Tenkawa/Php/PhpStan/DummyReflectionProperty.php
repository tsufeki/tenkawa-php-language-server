<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PHPStan\Reflection\Php\PhpPropertyReflection;

class DummyReflectionProperty extends \ReflectionProperty
{
    /**
     * @var PhpPropertyReflection
     */
    private $propertyReflection;

    /**
     * @var string
     */
    private $propertyName;

    public function __construct(PhpPropertyReflection $propertyReflection, string $name)
    {
        $this->propertyReflection = $propertyReflection;
        $this->propertyName = $name;
    }

    public function getName()
    {
        return $this->propertyName;
    }

    public function getDeclaringClass()
    {
        $declaringClass = $this->propertyReflection->getDeclaringClass();
        assert($declaringClass instanceof IndexClassReflection);

        return new DummyReflectionClass($declaringClass);
    }

    public function isPrivate()
    {
        return $this->propertyReflection->isPrivate();
    }

    public function isProtected()
    {
        return !$this->propertyReflection->isPublic() && !$this->propertyReflection->isPrivate();
    }

    public function isStatic()
    {
        return $this->propertyReflection->isStatic();
    }
}
