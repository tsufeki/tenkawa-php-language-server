<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use Tsufeki\Tenkawa\Php\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassConst;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedMethod;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedProperty;
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

        if ($symbol instanceof DefinitionSymbol) {
            if (in_array($symbol->kind, GlobalSymbol::KINDS, true)) {
                return yield $this->getGlobalReflectionFromSymbol($symbol);
            }

            if (in_array($symbol->kind, MemberSymbol::KINDS, true)) {
                return yield $this->getMemberReflectionFromSymbol($symbol);
            }
        }

        return [];
    }

    /**
     * @resolve Element[]
     */
    public function getReflectionOrConstructorFromSymbol(Symbol $symbol): \Generator
    {
        if ($symbol instanceof GlobalSymbol && $symbol->kind === GlobalSymbol::CLASS_ && $symbol->isNewExpression) {
            /** @var ResolvedClassLike|null $resolvedClass */
            $resolvedClass = yield $this->classResolver->resolve($symbol->referencedNames[0], $symbol->document);
            if ($resolvedClass !== null && isset($resolvedClass->methods['__construct'])) {
                return [$resolvedClass->methods['__construct']];
            }
        }

        return yield $this->getReflectionFromSymbol($symbol);
    }

    /**
     * @param GlobalSymbol|DefinitionSymbol $symbol
     *
     * @resolve Element[]
     */
    private function getGlobalReflectionFromSymbol(Symbol $symbol): \Generator
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
     * @param MemberSymbol|DefinitionSymbol $symbol
     *
     * @resolve Element[]
     */
    private function getMemberReflectionFromSymbol(Symbol $symbol): \Generator
    {
        $objectType = $symbol instanceof MemberSymbol ? $symbol->objectType : new ObjectType($symbol->nameContext->class);
        $allElements = yield $this->getMemberReflectionForType($objectType, $symbol->kind, $symbol->document);

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
     * @resolve (ResolvedClassConst|ResolvedProperty|ResolvedMethod)[][] member name => member array
     */
    public function getMemberReflectionForType(
        Type $type,
        string $kind,
        Document $document,
        bool $strict = false
    ): \Generator {
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

        if ($type instanceof IntersectionType || ($type instanceof UnionType && !$strict)) {
            /** @var array<int,array<string,Element[]>> $members */
            $members = yield array_map(function (Type $subtype) use ($kind, $document, $strict) {
                return $this->getMemberReflectionForType($subtype, $kind, $document, $strict);
            }, $type->types);

            return $this->deduplicateMembers(array_merge_recursive(...$members));
        }

        if ($type instanceof UnionType) {
            /** @var array<int,array<string,Element[]>> $members */
            $members = yield array_map(function (Type $subtype) use ($kind, $document, $strict) {
                return $this->getMemberReflectionForType($subtype, $kind, $document, $strict);
            }, $type->types);

            if ($members === []) {
                return [];
            }

            /** @var string[][] $keys */
            $keys = array_map('array_keys', $members);
            $keys = count($keys) === 1 ? $keys[0] : array_intersect(...$keys);

            $elements = [];
            foreach ($keys as $key) {
                $elements[$key] = array_merge(...array_column($members, $key));
            }

            return $this->deduplicateMembers($elements);
        }

        return [];
    }

    /**
     * @param array<string,Element[]> $members
     *
     * @return array<string,Element[]>
     */
    private function deduplicateMembers(array $members): array
    {
        return array_map(function (array $memberVariants) {
            return array_unique($memberVariants, SORT_REGULAR);
        }, $members);
    }
}
