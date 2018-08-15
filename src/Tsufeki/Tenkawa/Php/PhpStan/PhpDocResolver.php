<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PhpParser\NodeTraverser;
use PHPStan\Analyser\NameScope;
use PHPStan\Parser\Parser;
use PHPStan\PhpDoc\PhpDocStringResolver;
use PHPStan\PhpDoc\ResolvedPhpDocBlock;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\FileTypeMapper;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassLike;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\Cache;
use Tsufeki\Tenkawa\Server\Utils\InfiniteRecursionMarker;
use Tsufeki\Tenkawa\Server\Utils\SyncAsync;

class PhpDocResolver extends FileTypeMapper
{
    /**
     * @var Parser
     */
    private $phpParser;

    /**
     * @var PhpDocStringResolver
     */
    private $phpDocStringResolver;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    /**
     * @var SyncAsync
     */
    private $syncAsync;

    /**
     * @var Document|null
     */
    private $document;

    /**
     * @var Cache|null
     */
    private $cache;

    public function __construct(
        Parser $phpParser,
        PhpDocStringResolver $phpDocStringResolver,
        ReflectionProvider $reflectionProvider,
        SyncAsync $syncAsync
    ) {
        $this->phpParser = $phpParser;
        $this->phpDocStringResolver = $phpDocStringResolver;
        $this->reflectionProvider = $reflectionProvider;
        $this->syncAsync = $syncAsync;
    }

    public function setDocument(?Document $document)
    {
        $this->document = $document;
    }

    public function setCache(?Cache $cache)
    {
        $this->cache = $cache;
    }

    public function getResolvedPhpDoc(
        string $filename,
        ?string $className,
        ?string $traitName,
        string $docComment
    ): ResolvedPhpDocBlock {
        if ($this->document === null || $this->cache === null) {
            throw new ShouldNotHappenException();
        }

        $uri = Uri::fromFilesystemPath($filename);
        $nameContext = null;

        if ($this->document->getUri()->equals($uri)) {
            $nameContext = $this->findDocCommentInAst($filename, $docComment);
        } else {
            $nameContext = $this->findDocCommentInIndex($uri, $docComment);
        }

        $nameContext = $nameContext ?? new NameContext();
        $nameContext->class = $className ? '\\' . $className : null;

        $docBlock = $this->getResolvedPhpDocForNameContext($docComment, $nameContext);

        return $docBlock;
    }

    private function findDocCommentInAst(string $filename, string $docComment): ?NameContext
    {
        if ($this->cache === null) {
            throw new ShouldNotHappenException();
        }

        $nameContexts = $this->cache->get("phpdoc_resolver.name_contexts.$filename");
        if ($nameContexts !== null) {
            return $nameContexts[$docComment] ?? null;
        }

        $nodes = $this->phpParser->parseFile($filename);
        $visitor = new PhpDocResolverVisitor(Uri::fromFilesystemPath($filename));
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($nodes);
        $nameContexts = $visitor->getNameContexts();

        $this->cache->set("phpdoc_resolver.name_contexts.$filename", $nameContexts);

        return $nameContexts[$docComment] ?? null;
    }

    private function findDocCommentInIndex(Uri $uri, string $docComment): ?NameContext
    {
        if ($this->document === null) {
            throw new ShouldNotHappenException();
        }

        /** @var ClassLike[] $classes */
        $classes = $this->syncAsync->callAsync(
            $this->reflectionProvider->getClassesFromUri($this->document, $uri)
        );

        foreach ($classes as $class) {
            if (($class->docComment->text ?? null) === $docComment) {
                return $class->nameContext;
            }

            foreach (array_merge($class->methods, $class->properties, $class->consts) as $member) {
                if (($member->docComment->text ?? null) === $docComment) {
                    return $member->nameContext;
                }
            }
        }

        /** @var Function_[] $functions */
        $functions = $this->syncAsync->callAsync(
            $this->reflectionProvider->getFunctionsFromUri($this->document, $uri)
        );

        foreach ($functions as $function) {
            if (($function->docComment->text ?? null) === $docComment) {
                return $function->nameContext;
            }
        }

        /** @var Const_[] $consts */
        $consts = $this->syncAsync->callAsync(
            $this->reflectionProvider->getConstsFromUri($this->document, $uri)
        );

        foreach ($consts as $const) {
            if (($const->docComment->text ?? null) === $docComment) {
                return $const->nameContext;
            }
        }

        return null;
    }

    /**
     * @param Element|ResolvedClassLike $element
     */
    public function getResolvedPhpDocForReflectionElement($element): ResolvedPhpDocBlock
    {
        return $this->getResolvedPhpDocForNameContext(
            $element->docComment->text ?? '/** */',
            $element->docComment->nameContext ?? $element->nameContext
        );
    }

    private function getResolvedPhpDocForNameContext(string $docComment, NameContext $context): ResolvedPhpDocBlock
    {
        if ($this->cache === null) {
            throw new ShouldNotHappenException();
        }

        $key = 'phpdoc_resolver.doc_block.' . sha1(serialize($context) . $docComment);
        $docBlock = $this->cache->get($key);
        if ($docBlock === InfiniteRecursionMarker::get()) {
            return $this->phpDocStringResolver->resolve('/** */', new NameScope(null, []));
        }
        if ($docBlock !== null) {
            return $docBlock;
        }
        $this->cache->set($key, InfiniteRecursionMarker::get());

        $uses = [];
        foreach ($context->uses as $alias => $name) {
            $uses[strtolower($alias)] = ltrim($name, '\\');
        }

        $nameScope = new NameScope(
            ltrim($context->namespace, '\\') ?: null,
            $uses,
            ltrim((string)$context->class, '\\') ?: null
        );

        $docBlock = $this->phpDocStringResolver->resolve($docComment, $nameScope);
        $this->cache->set($key, $docBlock);

        return $docBlock;
    }
}
