<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\ClassConstantReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\MissingMethodFromReflectionException;
use PHPStan\Reflection\MissingPropertyFromReflectionException;
use PHPStan\Reflection\Php\PhpMethodReflection;
use PHPStan\Reflection\Php\PhpPropertyReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\ShouldNotHappenException;
use Tsufeki\Tenkawa\Php\Reflection\ResolvedClassLike;

//TODO annotation property/method and universal crate extension
class IndexClassReflection extends ClassReflection
{
    /**
     * @var ResolvedClassLike
     */
    private $class;

    /**
     * @var Broker
     */
    private $broker;

    /**
     * @var PhpDocResolver
     */
    private $phpDocResolver;

    /**
     * @var PropertiesClassReflectionExtension[]
     */
    private $propertiesClassReflectionExtensions;

    /**
     * @var MethodsClassReflectionExtension[]
     */
    private $methodsClassReflectionExtensions;

    /**
     * @var PhpMethodReflection[]
     */
    private $methods = [];

    /**
     * @var PhpPropertyReflection[]
     */
    private $properties = [];

    /**
     * @var ClassConstantReflection[]
     */
    private $constants = [];

    /**
     * @param PropertiesClassReflectionExtension[] $propertiesClassReflectionExtensions
     * @param MethodsClassReflectionExtension[]    $methodsClassReflectionExtensions
     */
    public function __construct(
        ResolvedClassLike $class,
        Broker $broker,
        PhpDocResolver $phpDocResolver,
        array $propertiesClassReflectionExtensions,
        array $methodsClassReflectionExtensions
    ) {
        $this->class = $class;
        $this->broker = $broker;
        $this->phpDocResolver = $phpDocResolver;
        $this->propertiesClassReflectionExtensions = $propertiesClassReflectionExtensions;
        $this->methodsClassReflectionExtensions = $methodsClassReflectionExtensions;
    }

    public function getNativeReflection(): \ReflectionClass
    {
        return new DummyReflectionClass($this);
    }

    /**
     * @return string|false
     */
    public function getFileName()
    {
        if ($this->class->location === null) {
            return false;
        }

        return $this->class->location->uri->getFilesystemPath();
    }

    /**
     * @return false|ClassReflection
     */
    public function getParentClass()
    {
        if ($this->class->parentClass === null) {
            return false;
        }

        return $this->broker->getClass($this->class->parentClass->name);
    }

    public function getName(): string
    {
        return ltrim($this->class->name, '\\');
    }

    public function getDisplayName(): string
    {
        return $this->getName();
    }

    /**
     * @return int[]
     */
    public function getClassHierarchyDistances(): array
    {
        //TODO
        throw new ShouldNotHappenException();
    }

    public function hasProperty(string $propertyName): bool
    {
        //TODO extensions & scope

        return $this->hasNativeProperty($propertyName);
    }

    public function hasMethod(string $methodName): bool
    {
        //TODO extensions & scope

        return $this->hasNativeMethod($methodName);
    }

    public function getMethod(string $methodName, Scope $scope): MethodReflection
    {
        //TODO extensions & scope

        return $this->getNativeMethod($methodName);
    }

    public function hasNativeMethod(string $methodName): bool
    {
        $methodName = strtolower($methodName);

        return isset($this->class->methods[$methodName]);
    }

    public function getNativeMethod(string $methodName): PhpMethodReflection
    {
        $methodName = strtolower($methodName);
        if (!$this->hasNativeMethod($methodName)) {
            throw new MissingMethodFromReflectionException($this->getName(), $methodName);
        }

        if (isset($this->methods[$methodName])) {
            return $this->methods[$methodName];
        }

        $method = $this->class->methods[$methodName];

        return $this->methods[$methodName] = new IndexMethodReflection(
            $this->broker->getClass((string)$method->nameContext->class),
            $method,
            $this->phpDocResolver
        );
    }

    public function getProperty(string $propertyName, Scope $scope): PropertyReflection
    {
        //TODO extensions & scope

        return $this->getNativeProperty($propertyName);
    }

    public function hasNativeProperty(string $propertyName): bool
    {
        return isset($this->class->properties[$propertyName]);
    }

    public function getNativeProperty(string $propertyName): PhpPropertyReflection
    {
        if (!$this->hasNativeProperty($propertyName)) {
            throw new MissingPropertyFromReflectionException($this->getName(), $propertyName);
        }

        if (isset($this->properties[$propertyName])) {
            return $this->properties[$propertyName];
        }

        $property = $this->class->properties[$propertyName];

        return $this->properties[$propertyName] = new IndexPropertyReflection(
            $this->broker->getClass((string)$property->nameContext->class),
            $property,
            $this->phpDocResolver
        );
    }

    public function isAbstract(): bool
    {
        return $this->class->abstract;
    }

    public function isFinal(): bool
    {
        return $this->class->final;
    }

    public function isInterface(): bool
    {
        return $this->class->isInterface;
    }

    public function isTrait(): bool
    {
        return $this->class->isTrait;
    }

    public function isAnonymous(): bool
    {
        return false;
    }

    public function isSubclassOf(string $className): bool
    {
        $className = '\\' . ltrim($className, '\\');
        /** @var ResolvedClassLike|null $parent */
        $parent = $this->class;
        while ($parent !== null) {
            if ($parent->name === $className) {
                return true;
            }
            $parent = $parent->parentClass;
        }

        foreach ($this->class->interfaces as $interface) {
            if ($interface->name === $className) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return ClassReflection[]
     */
    public function getParents(): array
    {
        $parents = [];
        $parent = $this->getParentClass();
        while ($parent !== false) {
            $parents[] = $parent;
            $parent = $parent->getParentClass();
        }

        return $parents;
    }

    /**
     * @return ClassReflection[]
     */
    public function getInterfaces(): array
    {
        return array_map(function (ResolvedClassLike $interface) {
            return $this->broker->getClass($interface->name);
        }, $this->class->interfaces);
    }

    /**
     * @return string[]
     */
    public function getInterfaceNames(): array
    {
        return array_map(function (ClassReflection $interface) {
            return $interface->getName();
        }, $this->getInterfaces());
    }

    /**
     * @return ClassReflection[]
     */
    public function getTraits(): array
    {
        return array_map(function (ResolvedClassLike $trait) {
            return $this->broker->getClass($trait->name);
        }, $this->class->traits);
    }

    /**
     * @return string[]
     */
    public function getParentClassesNames(): array
    {
        return array_map(function (ClassReflection $classReflection) {
            return $classReflection->getName();
        }, $this->getParents());
    }

    public function hasConstant(string $name): bool
    {
        return isset($this->class->consts[$name]);
    }

    public function getConstant(string $name): ClassConstantReflection
    {
        if (!$this->hasConstant($name)) {
            throw new ShouldNotHappenException();
        }

        if (isset($this->constants[$name])) {
            return $this->constants[$name];
        }

        $const = $this->class->consts[$name];

        return $this->constants[$name] = new IndexClassConstantReflection(
            $this->broker->getClass((string)$const->nameContext->class),
            $const
        );
    }

    public function hasTraitUse(string $traitName): bool
    {
        $traitName = '\\' . ltrim($traitName, '\\');
        foreach ($this->class->traits as $trait) {
            if ($traitName === $trait->name) {
                return true;
            }
        }

        $parentClass = $this->getParentClass();

        return $parentClass !== false && $parentClass->hasTraitUse($traitName);
    }
}
