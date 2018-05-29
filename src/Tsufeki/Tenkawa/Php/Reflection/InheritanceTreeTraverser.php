<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Utils\Cache;

class InheritanceTreeTraverser
{
    /**
     * @var ClassResolver
     */
    private $classResolver;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    public function __construct(ClassResolver $classResolver, ReflectionProvider $reflectionProvider)
    {
        $this->classResolver = $classResolver;
        $this->reflectionProvider = $reflectionProvider;
    }

    /**
     * @param InheritanceTreeVisitor[] $visitors
     */
    public function traverse(string $className, array $visitors, Document $document): \Generator
    {
        yield $this->traverseClass($className, $visitors, $document, new Cache());
    }

    /**
     * @param InheritanceTreeVisitor[] $visitors
     */
    private function traverseClass(string $className, array $visitors, Document $document, Cache $cache): \Generator
    {
        $class = yield $this->classResolver->resolve($className, $document, $cache);
        if ($class === null) {
            return;
        }

        foreach ($visitors as $visitor) {
            yield $visitor->enter($class);
        }

        foreach (yield $this->reflectionProvider->getInheritingClasses($document, $className) as $inheritingClassName) {
            yield $this->traverseClass($inheritingClassName, $visitors, $document, $cache);
        }

        foreach ($visitors as $visitor) {
            yield $visitor->leave($class);
        }
    }
}
