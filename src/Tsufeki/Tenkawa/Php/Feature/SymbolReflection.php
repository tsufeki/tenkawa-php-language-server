<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use Tsufeki\Tenkawa\Php\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Method;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Php\TypeInference\IntersectionType;
use Tsufeki\Tenkawa\Php\TypeInference\ObjectType;
use Tsufeki\Tenkawa\Php\TypeInference\Type;
use Tsufeki\Tenkawa\Php\TypeInference\UnionType;
use Tsufeki\Tenkawa\Server\Document\Document;

class SymbolReflection
{
    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    /**
     * @var ClassResolver
     */
    private $classResolver;

    public function __construct(ReflectionProvider $reflectionProvider, ClassResolver $classResolver)
    {
        $this->reflectionProvider = $reflectionProvider;
        $this->classResolver = $classResolver;
    }

    /**
     * @resolve Element[]
     */
    public function getReflectionFromSymbol(Symbol $symbol): \Generator
    {
        if ($symbol instanceof GlobalSymbol) {
            return yield $this->getGlobalReflectionFromSymbol($symbol);
        }

        if ($symbol instanceof MemberSymbol) {
            return yield $this->getMemberReflectionFromSymbol($symbol);
        }

        return [];
    }

    /**
     * @resolve Element[]
     */
    private function getGlobalReflectionFromSymbol(GlobalSymbol $symbol): \Generator
    {
        foreach ($symbol->referencedNames as $name) {
            $elements = null;
            if ($symbol->kind === GlobalSymbol::CLASS_) {
                $elements = yield $this->reflectionProvider->getClass($symbol->document, $name);
            } elseif ($symbol->kind === GlobalSymbol::FUNCTION_) {
                $elements = yield $this->reflectionProvider->getFunction($symbol->document, $name);
            } elseif ($symbol->kind === GlobalSymbol::CONST_) {
                $elements = yield $this->reflectionProvider->getConst($symbol->document, $name);
            }

            if (!empty($elements)) {
                return $elements;
            }
        }

        return [];
    }

    /**
     * @resolve Element[]
     */
    private function getMemberReflectionFromSymbol(MemberSymbol $symbol): \Generator
    {
        $allElements = yield $this->getMemberReflectionForType($symbol->objectType, $symbol->kind, $symbol->document);

        foreach ($symbol->referencedNames as $name) {
            if ($symbol->kind === MemberSymbol::METHOD) {
                $name = strtolower($name);
            }

            $elements = $allElements[$name] ?? [];
            if (!empty($elements)) {
                return $elements;
            }
        }

        return [];
    }

    /**
     * @resolve Element[][] member name => member array
     */
    public function getMemberReflectionForType(Type $type, string $kind, Document $document): \Generator
    {
        if ($type instanceof ObjectType) {
            $resolvedClass = yield $this->classResolver->resolve($type->class, $document);
            if ($resolvedClass === null) {
                return [];
            }

            $members = [];
            if ($kind === MemberSymbol::PROPERTY) {
                $members = $resolvedClass->properties;
            } elseif ($kind === MemberSymbol::METHOD) {
                $members = $resolvedClass->methods;
            } elseif ($kind === MemberSymbol::CLASS_CONST) {
                $members = $resolvedClass->consts;
            }

            return array_map(function ($member) {
                return [$member];
            }, $members);
        }

        if ($type instanceof IntersectionType) {
            $members = yield array_map(function (Type $subtype) use ($kind, $document) {
                return $this->getMemberReflectionForType($subtype, $kind, $document);
            }, $type->types);

            return array_merge_recursive(...$members);
        }

        if ($type instanceof UnionType) {
            $subtypes = array_values(array_filter($type->types, function (Type $subtype) {
                return $subtype instanceof ObjectType
                    || $subtype instanceof UnionType
                    || $subtype instanceof IntersectionType;
            }));

            if (empty($subtypes)) {
                return [];
            }

            $members = yield array_map(function (Type $subtype) use ($kind, $document) {
                return $this->getMemberReflectionForType($subtype, $kind, $document);
            }, $subtypes);

            $elements = [];
            foreach (array_keys(count($members) === 1 ? $members[0] : array_intersect_key(...$members)) as $key) {
                $elements[$key] = array_merge(...array_column($members, $key));
            }

            return $elements;
        }

        return [];
    }
}
