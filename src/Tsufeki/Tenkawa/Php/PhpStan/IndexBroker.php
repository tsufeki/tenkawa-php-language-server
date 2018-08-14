<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\AnonymousClassNameHelper;
use PHPStan\Broker\Broker;
use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Broker\FunctionNotFoundException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\Php\PhpFunctionReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\SignatureMap\SignatureMapProvider;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use Tsufeki\Tenkawa\Php\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Php\Reflection\ConstExprEvaluator;
use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\NameHelper;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\Cache;
use Tsufeki\Tenkawa\Server\Utils\SyncAsync;

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
     * @var ConstExprEvaluator
     */
    private $constExprEvaluator;

    /**
     * @var SyncAsync
     */
    private $syncAsync;

    /**
     * @var PhpDocResolver
     */
    private $phpDocResolver;

    /**
     * @var SignatureVariantFactory
     */
    private $signatureVariantFactory;

    /**
     * @var Document|null
     */
    private $document;

    /**
     * @var Cache|null
     */
    private $cache;

    /**
     * @param PropertiesClassReflectionExtension[]     $propertiesClassReflectionExtensions
     * @param MethodsClassReflectionExtension[]        $methodsClassReflectionExtensions
     * @param DynamicMethodReturnTypeExtension[]       $dynamicMethodReturnTypeExtensions
     * @param DynamicStaticMethodReturnTypeExtension[] $dynamicStaticMethodReturnTypeExtensions
     * @param DynamicFunctionReturnTypeExtension[]     $dynamicFunctionReturnTypeExtensions
     * @param string[]                                 $universalObjectCratesClasses
     */
    public function __construct(
        array $propertiesClassReflectionExtensions,
        array $methodsClassReflectionExtensions,
        array $dynamicMethodReturnTypeExtensions,
        array $dynamicStaticMethodReturnTypeExtensions,
        array $dynamicFunctionReturnTypeExtensions,
        ReflectionProvider $reflectionProvider,
        ClassResolver $classResolver,
        ConstExprEvaluator $constExprEvaluator,
        SyncAsync $syncAsync,
        PhpDocResolver $phpDocResolver,
        SignatureVariantFactory $signatureVariantFactory,
        array $universalObjectCratesClasses
    ) {
        parent::__construct(
            $propertiesClassReflectionExtensions,
            $methodsClassReflectionExtensions,
            $dynamicMethodReturnTypeExtensions,
            $dynamicStaticMethodReturnTypeExtensions,
            $dynamicFunctionReturnTypeExtensions,
            new DummyFunctionReflectionFactory(),
            $phpDocResolver,
            // stubs:
            new class() extends SignatureMapProvider {
                public function __construct()
                {
                }
            },
            new class() extends Standard {
                public function __construct()
                {
                }
            },
            new class() extends AnonymousClassNameHelper {
                public function __construct()
                {
                }
            },
            new class() extends DocumentParser {
                public function __construct()
                {
                }
            },
            $universalObjectCratesClasses,
            ''
        );

        $this->propertiesReflectionExtensions = $propertiesClassReflectionExtensions;
        $this->methodsReflectionExtensions = $methodsClassReflectionExtensions;
        $this->reflectionProvider = $reflectionProvider;
        $this->classResolver = $classResolver;
        $this->constExprEvaluator = $constExprEvaluator;
        $this->syncAsync = $syncAsync;
        $this->phpDocResolver = $phpDocResolver;
        $this->signatureVariantFactory = $signatureVariantFactory;

        self::registerInstance($this);
    }

    public function setDocument(?Document $document)
    {
        $this->document = $document;
    }

    public function setCache(?Cache $cache)
    {
        $this->cache = $cache;
    }

    public function getClass(string $className): ClassReflection
    {
        if ($this->document === null || $this->cache === null) {
            throw new ShouldNotHappenException();
        }

        $className = '\\' . ltrim($className, '\\');
        $classReflection = $this->cache->get("broker.class.$className");
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
            $this->signatureVariantFactory,
            $this->propertiesReflectionExtensions,
            $this->methodsReflectionExtensions
        );
        $this->cache->set("broker.class.$className", $classReflection);

        return $classReflection;
    }

    public function getAnonymousClassReflection(New_ $node, Scope $scope): ClassReflection
    {
        if (!$node->class instanceof Class_) {
            throw new \PHPStan\ShouldNotHappenException();
        }

        $scopeFile = $scope->getFile();
        $trait = $scope->getTraitReflection();
        if ($trait !== null) {
            $scopeFile = $trait->getFileName() ?: $scopeFile;
        }

        $className = NameHelper::getAnonymousClassName(Uri::fromFilesystemPath($scopeFile), $node->class);

        return $this->getClass($className);
    }

    public function getClassFromReflection(\ReflectionClass $reflectionClass, string $displayName, ?string $anonymousFilename): ClassReflection
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

    public function getFunction(Name $nameNode, ?Scope $scope): FunctionReflection
    {
        if ($this->document === null || $this->cache === null) {
            throw new ShouldNotHappenException();
        }

        foreach ($this->getNameCandidates($nameNode, $scope) as $name) {
            $functionReflection = $this->cache->get("broker.function.$name");
            if ($functionReflection !== null) {
                return $functionReflection;
            }

            $function = $this->syncAsync->callAsync($this->reflectionProvider->getFunction($this->document, $name))[0] ?? null;
            if ($function === null) {
                continue;
            }

            $functionReflection = new IndexFunctionReflection($function, $this->phpDocResolver, $this->signatureVariantFactory);
            $this->cache->set("broker.function.$name", $functionReflection);

            return $functionReflection;
        }

        throw new FunctionNotFoundException((string)$nameNode);
    }

    public function hasFunction(Name $nameNode, ?Scope $scope): bool
    {
        return $this->resolveFunctionName($nameNode, $scope) !== null;
    }

    public function hasCustomFunction(Name $nameNode, ?Scope $scope): bool
    {
        $resolved = $this->resolveFunctionName($nameNode, $scope);

        return $resolved !== null && $this->signatureVariantFactory->isCustom($resolved);
    }

    public function getCustomFunction(Name $nameNode, ?Scope $scope): PhpFunctionReflection
    {
        if (!$this->hasCustomFunction($nameNode, $scope)) {
            throw new ShouldNotHappenException();
        }

        $functionReflection = $this->getFunction($nameNode, $scope);
        assert($functionReflection instanceof PhpFunctionReflection);

        return $functionReflection;
    }

    public function resolveFunctionName(Name $nameNode, ?Scope $scope): ?string
    {
        try {
            return ltrim($this->getFunction($nameNode, $scope)->getName(), '\\');
        } catch (FunctionNotFoundException $e) {
            return null;
        }
    }

    public function hasConstant(Name $nameNode, ?Scope $scope): bool
    {
        return $this->resolveConstantName($nameNode, $scope) !== null;
    }

    public function resolveConstantName(Name $nameNode, ?Scope $scope): ?string
    {
        // TODO halt compiler

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
     * @return mixed
     */
    public function getConstantValue(string $name)
    {
        if ($this->document === null) {
            throw new ShouldNotHappenException();
        }

        $name = '\\' . ltrim($name, '\\');
        $const = $this->syncAsync->callAsync($this->reflectionProvider->getConst($this->document, $name))[0] ?? null;
        if ($const === null) {
            return null;
        }

        return $this->getConstantValueFromReflection($const);
    }

    /**
     * @return mixed
     */
    public function getConstantValueFromReflection(Const_ $const)
    {
        if ($this->document === null) {
            throw new ShouldNotHappenException();
        }

        return $this->syncAsync->callAsync($this->constExprEvaluator->getConstValue($const, $this->document));
    }

    /**
     * @return string[]
     */
    private function getNameCandidates(
        Name $nameNode,
        ?Scope $scope
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
