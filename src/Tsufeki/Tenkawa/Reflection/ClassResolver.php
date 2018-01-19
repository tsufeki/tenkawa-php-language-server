<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Reflection;

use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Reflection\Element\Method;
use Tsufeki\Tenkawa\Reflection\Element\Property;

class ClassResolver
{
    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    /**
     * @resolve ResolvedClassLike|null
     */
    public function resolve(string $className, Document $document): \Generator
    {
        /** @var ClassLike[] $classes */
        $classes = yield $this->reflectionProvider->getClass($document, $className);
        if (empty($classes)) {
            return null;
        }
        $class = $classes[0];

        $resolved = new ResolvedClassLike();
        $resolved->name = $class->name;
        $resolved->location = $class->location;
        $resolved->docComment = $class->docComment;
        $resolved->nameContext = $class->nameContext;
        $resolved->isClass = $class->isClass;
        $resolved->isInterface = $class->isInterface;
        $resolved->isTrait = $class->isTrait;
        $resolved->abstract = $class->abstract;
        $resolved->final = $class->final;

        if ($class->parentClass !== null) {
            $resolved->parentClass = yield $this->resolve($class->parentClass, $document);
        }

        foreach ($class->interfaces as $interfaceName) {
            $resolved->interfaces[] = yield $this->resolve($interfaceName, $document);
        }
        $resolved->interfaces = array_filter($resolved->interfaces);

        foreach ($class->traits as $traitName) {
            $resolved->traits[] = yield $this->resolve($traitName, $document);
        }
        $resolved->traits = array_filter($resolved->traits);

        foreach ($resolved->interfaces as $interface) {
            $resolved->methods = $this->mergeMembers($resolved->methods, $interface->methods);
            $resolved->consts = $this->mergeMembers($resolved->consts, $interface->consts);
        }

        if ($resolved->parentClass !== null) {
            $resolved->methods = $this->mergeMembers($resolved->methods, $resolved->parentClass->methods);
            $resolved->properties = $this->mergeMembers($resolved->properties, $resolved->parentClass->properties);
            $resolved->consts = $this->mergeMembers($resolved->consts, $resolved->parentClass->consts);
        }

        foreach ($resolved->traits as $trait) {
            $resolved->properties = $this->mergeTraitProperties($resolved->properties, $trait, $class);
            $resolved->methods = $this->mergeTraitMethods($resolved->methods, $trait, $class);
        }

        $resolved->methods = array_replace($resolved->methods, $this->indexMembers($class->methods, true));
        $resolved->properties = array_replace($resolved->properties, $this->indexMembers($class->properties));
        $resolved->consts = array_replace($resolved->consts, $this->indexMembers($class->consts));

        if ($resolved->parentClass !== null) {
            $resolved->interfaces = array_merge($resolved->interfaces, $resolved->parentClass->interfaces);
        }

        return $resolved;
    }

    private function mergeMembers(array $members, array $superMembers): array
    {
        $superMembers = array_filter($superMembers, function ($member) {
            return $member->accessibility !== ClassLike::M_PRIVATE;
        });

        return array_replace($members, $superMembers);
    }

    /**
     * @param Property[] $properties
     *
     * @return Property[]
     */
    private function mergeTraitProperties(array $properties, ResolvedClassLike $trait, ClassLike $class): array
    {
        $traitProperties = $trait->properties;

        foreach ($traitProperties as &$property) {
            $property = clone $property;
            $property->nameContext = clone $property->nameContext;
            $property->nameContext->class = $class->name;
        }
        unset($property);

        return array_replace($properties, $traitProperties);
    }

    /**
     * @param Method[] $methods
     *
     * @return Method[]
     */
    private function mergeTraitMethods(array $methods, ResolvedClassLike $trait, ClassLike $class): array
    {
        $traitName = strtolower($trait->name);
        $traitMethods = $trait->methods;

        foreach ($class->traitAliases as $alias) {
            if (($alias->trait === null || strtolower($alias->trait) === $traitName) && isset($traitMethods[strtolower($alias->method)])) {
                $method = clone $traitMethods[strtolower($alias->method)];
                $method->name = $alias->newName ?? $method->name;
                $method->accessibility = $alias->newAccessibility ?? $method->accessibility;
                $traitMethods[strtolower($method->name)] = $method;
            }
        }

        foreach ($class->traitInsteadOfs as $insteadOf) {
            foreach ($insteadOf->insteadOfs as $insteadOfTrait) {
                if (strtolower($insteadOfTrait) === $traitName) {
                    unset($traitMethods[strtolower($insteadOf->method)]);
                }
            }
        }

        foreach ($traitMethods as &$method) {
            $method = clone $method;
            $method->nameContext = clone $method->nameContext;
            $method->nameContext->class = $class->name;
        }
        unset($method);

        return array_replace($methods, $traitMethods);
    }

    private function indexMembers(array $members, bool $lowercase = false): array
    {
        $indexedMembers = [];
        foreach ($members as $member) {
            $name = $lowercase ? strtolower($member->name) : $member->name;
            $indexedMembers[$name] = $member;
        }

        return $indexedMembers;
    }
}
