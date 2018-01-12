<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Broker\FunctionNotFoundException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Utils\SyncAsync;

class IndexBroker extends Broker
{
    /**
     * @var PropertiesClassReflectionExtension[]
     */
    private $propertiesReflectionExtensions;

    /**
     * @var MethodsClassReflectionExtension[]
     */
    private $methodsReflectionExtensions;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    /**
     * @var ClassResolver
     */
    private $classResolver;

    /**
     * @var SyncAsync
     */
    private $syncAsync;

    /**
     * @var PhpDocResolver
     */
    private $phpDocResolver;

    /**
     * @var Document|null
     */
    private $document;

    /**
     * @param PropertiesClassReflectionExtension[]     $propertiesClassReflectionExtensions
     * @param MethodsClassReflectionExtension[]        $methodsClassReflectionExtensions
     * @param DynamicMethodReturnTypeExtension[]       $dynamicMethodReturnTypeExtensions
     * @param DynamicStaticMethodReturnTypeExtension[] $dynamicStaticMethodReturnTypeExtensions
     * @param DynamicFunctionReturnTypeExtension[]     $dynamicFunctionReturnTypeExtensions
     */
    public function __construct(
        array $propertiesClassReflectionExtensions,
        array $methodsClassReflectionExtensions,
        array $dynamicMethodReturnTypeExtensions,
        array $dynamicStaticMethodReturnTypeExtensions,
        array $dynamicFunctionReturnTypeExtensions,
        ReflectionProvider $reflectionProvider,
        ClassResolver $classResolver,
        SyncAsync $syncAsync,
        PhpDocResolver $phpDocResolver
    ) {
        parent::__construct(
            $propertiesClassReflectionExtensions,
            $methodsClassReflectionExtensions,
            $dynamicMethodReturnTypeExtensions,
            $dynamicStaticMethodReturnTypeExtensions,
            $dynamicFunctionReturnTypeExtensions,
            new DummyFunctionReflectionFactory(),
            $phpDocResolver
        );

        $this->propertiesReflectionExtensions = $propertiesClassReflectionExtensions;
        $this->methodsReflectionExtensions = $methodsClassReflectionExtensions;
        $this->reflectionProvider = $reflectionProvider;
        $this->classResolver = $classResolver;
        $this->syncAsync = $syncAsync;
        $this->phpDocResolver = $phpDocResolver;
    }

    public function setDocument(Document $document = null)
    {
        $this->document = $document;
    }

    public function getClass(string $className): ClassReflection
    {
        if ($this->document === null) {
            throw new ShouldNotHappenException();
        }

        $className = '\\' . ltrim($className, '\\');
        $classReflection = $this->document->get("phpstan.broker.class.$className");
        if ($classReflection !== null) {
            return $classReflection;
        }

        $class = $this->syncAsync->callAsync($this->classResolver->resolve($className, $this->document));
        if ($class === null) {
            throw new ClassNotFoundException($className);
        }

        $classReflection = new IndexClassReflection(
            $class,
            $this,
            $this->phpDocResolver,
            $this->propertiesReflectionExtensions,
            $this->methodsReflectionExtensions
        );
        $this->document->set("phpstan.broker.class.$className", $classReflection);

        return $classReflection;
    }

    public function getClassFromReflection(\ReflectionClass $reflectionClass, string $displayName, bool $anonymous): ClassReflection
    {
        throw new ShouldNotHappenException();
    }

    public function hasClass(string $className): bool
    {
        try {
            $this->getClass($className);

            return true;
        } catch (ClassNotFoundException $e) {
            return false;
        }
    }

    public function getFunction(Name $nameNode, Scope $scope = null): FunctionReflection
    {
        if ($this->document === null) {
            throw new ShouldNotHappenException();
        }

        $function = null;
        foreach ($this->getNameCandidates($nameNode, $scope) as $name) {
            $function = $this->syncAsync->callAsync($this->reflectionProvider->getFunction($this->document, $name))[0] ?? null;
            if ($function !== null) {
                break;
            }
        }

        if ($function === null) {
            throw new FunctionNotFoundException((string)$nameNode);
        }

        return new IndexFunctionReflection($function, $this->phpDocResolver);
    }

    public function hasFunction(Name $nameNode, Scope $scope = null): bool
    {
        return $this->resolveFunctionName($nameNode, $scope) !== null;
    }

    /**
     * @return string|null
     */
    public function resolveFunctionName(Name $nameNode, Scope $scope = null)
    {
        try {
            return ltrim($this->getFunction($nameNode, $scope)->getName(), '\\');
        } catch (FunctionNotFoundException $e) {
            return null;
        }
    }

    public function hasConstant(\PhpParser\Node\Name $nameNode, Scope $scope = null): bool
    {
        //TODO
        return false;
        // return $this->resolveConstantName($nameNode, $scope) !== null;
    }

    /**
     * @return string|null
     */
    public function resolveConstantName(Name $nameNode, Scope $scope = null)
    {
        if ($this->document === null) {
            throw new ShouldNotHappenException();
        }

        $const = null;
        foreach ($this->getNameCandidates($nameNode, $scope) as $name) {
            $const = $this->syncAsync->callAsync($this->reflectionProvider->getConst($this->document, $name))[0] ?? null;
            if ($const !== null) {
                return ltrim($const->name, '\\');
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function getNameCandidates(
        Name $nameNode,
        Scope $scope = null
    ): array {
        $candidates = [];
        $name = (string)$nameNode;

        if ($scope !== null && $scope->getNamespace() !== null && !$nameNode->isFullyQualified()) {
            $candidates[] = sprintf('\\%s\\%s', $scope->getNamespace(), $name);
        }

        $candidates[] = '\\' . $name;

        return $candidates;
    }
}
