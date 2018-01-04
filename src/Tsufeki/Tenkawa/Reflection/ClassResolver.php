<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Reflection;

use Tsufeki\Tenkawa\Reflection\Element\ClassLike;

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

    public function resolve(ClassLike $classLike): \Generator
    {
        $resolved = new ResolvedClassLike();
        $resolved->name = $classLike->name;
        $resolved->location = $classLike->location;
        $resolved->docComment = $classLike->docComment;
        $resolved->nameContext = $classLike->nameContext;
        $resolved->isClass = $classLike->isClass;
        $resolved->isInterface = $classLike->isInterface;
        $resolved->isTrait = $classLike->isTrait;
        $resolved->abstract = $classLike->abstract;
        $resolved->final = $classLike->final;
        $resolved->parentClass = $classLike->parentClass;
        $resolved->interfaces = $classLike->interfaces;
        $resolved->traits = $classLike->traits;

        //TODO

        return $classLike;
    }
}
