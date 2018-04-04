<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PHPStan\Analyser\Scope;
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

class IndexClassReflection extends ClassReflection
{
    /**
     * @var ResolvedClassLike
     */
    private $class;

    /**
     * @var IndexBroker
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
     * @var MethodReflection[][]
     */
    private $methods = [];

    /**
     * @var PhpMethodReflection[]
     */
    private $nativeMethods = [];

    /**
     * @var PropertyReflection[][]
     */
    private $properties = [];

    /**
     * @var PhpPropertyReflection[]
     */
    private $nativeProperties = [];

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
        IndexBroker $broker,
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
        throw new ShouldNotHappenException();
    }

    public function hasMethod(string $methodName): bool
    {
        $methodName = strtolower($methodName);
        $this->createMethods($methodName);

        return !empty($this->methods[$methodName]);
    }

    public function getMethod(string $methodName, Scope $scope): MethodReflection
    {
        $methodName = strtolower($methodName);
        $this->createMethods($methodName);

        $method = null;
        foreach ($this->methods[$methodName] ?? [] as $method) {
            if ($scope->canCallMethod($method)) {
                return $method;
            }
        }

        if ($method === null) {
            throw new MissingMethodFromReflectionException($this->getName(), $methodName);
        }

        return $method;
    }

    public function hasNativeMethod(string $methodName): bool
    {
        $methodName = strtolower($methodName);
        $this->createMethods($methodName);

        return !empty($this->nativeMethods[$methodName]);
    }

    public function getNativeMethod(string $methodName): PhpMethodReflection
    {
        $methodName = strtolower($methodName);
        $this->createMethods($methodName);

        $method = $this->nativeMethods[$methodName] ?? null;
        if ($method === null) {
            throw new MissingMethodFromReflectionException($this->getName(), $methodName);
        }

        return $method;
    }

    private function createMethods(string $methodName)
    {
        if (isset($this->methods[$methodName])) {
            return;
        }
        $this->methods[$methodName] = [];

        if (isset($this->class->methods[$methodName])) {
            $method = $this->class->methods[$methodName];
            $indexMethod = new IndexMethodReflection(
                $this->broker->getClass((string)$method->nameContext->class),
                $method,
                $this->phpDocResolver
            );

            $this->methods[$methodName][] = $indexMethod;
            if ($method->origin === null) {
                $this->nativeMethods[$methodName] = $indexMethod;
            }
        }

        foreach ($this->methodsClassReflectionExtensions as $extension) {
            if ($extension->hasMethod($this, $methodName)) {
                $this->methods[$methodName][] = $extension->getMethod($this, $methodName);
            }
        }
    }

    public function hasProperty(string $propertyName): bool
    {
        $this->createProperties($propertyName);

        return !empty($this->properties[$propertyName]);
    }

    public function getProperty(string $propertyName, Scope $scope): PropertyReflection
    {
        $this->createProperties($propertyName);

        $property = null;
        foreach ($this->properties[$propertyName] ?? [] as $property) {
            if ($scope->canAccessProperty($property)) {
                return $property;
            }
        }

        if ($property === null) {
            throw new MissingPropertyFromReflectionException($this->getName(), $propertyName);
        }

        return $property;
    }

    public function hasNativeProperty(string $propertyName): bool
    {
        $this->createProperties($propertyName);

        return !empty($this->nativeProperties[$propertyName]);
    }

    public function getNativeProperty(string $propertyName): PhpPropertyReflection
    {
        $this->createProperties($propertyName);

        $property = $this->nativeProperties[$propertyName] ?? null;
        if ($property === null) {
            throw new MissingPropertyFromReflectionException($this->getName(), $propertyName);
        }

        return $property;
    }

    private function createProperties(string $propertyName)
    {
        if (isset($this->properties[$propertyName])) {
            return;
        }
        $this->properties[$propertyName] = [];

        if (isset($this->class->properties[$propertyName])) {
            $property = $this->class->properties[$propertyName];
            $indexProperty = new IndexPropertyReflection(
                $this->broker->getClass((string)$property->nameContext->class),
                $property,
                $this->phpDocResolver
            );

            $this->properties[$propertyName][] = $indexProperty;
            if ($property->origin === null) {
                $this->nativeProperties[$propertyName] = $indexProperty;
            }
        }

        foreach ($this->propertiesClassReflectionExtensions as $extension) {
            if ($extension->hasProperty($this, $propertyName)) {
                $this->properties[$propertyName][] = $extension->getProperty($this, $propertyName);
            }
        }
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
            $const,
            $this->broker->getConstantValueFromReflection($const)
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
