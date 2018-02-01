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
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\SyncAsyncKernel;

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
     * @var SyncAsyncKernel
     */
    private $syncAsync;

    /**
     * @var Document|null
     */
    private $document;

    public function __construct(
        Parser $phpParser,
        PhpDocStringResolver $phpDocStringResolver,
        ReflectionProvider $reflectionProvider,
        SyncAsyncKernel $syncAsync
    ) {
        $this->phpParser = $phpParser;
        $this->phpDocStringResolver = $phpDocStringResolver;
        $this->reflectionProvider = $reflectionProvider;
        $this->syncAsync = $syncAsync;
    }

    public function setDocument(Document $document = null)
    {
        $this->document = $document;
    }

    public function getResolvedPhpDoc(string $filename, string $className = null, string $docComment): ResolvedPhpDocBlock
    {
        if ($this->document === null) {
            throw new ShouldNotHappenException();
        }

        //TODO infinite recursion guard
        $uri = Uri::fromFilesystemPath($filename);

        $nameContext = null;
        if ((string)$uri === (string)$this->document->getUri()) {
            $nodes = $this->phpParser->parseFile($filename);
            $visitor = new PhpDocResolverVisitor($docComment);
            $nodeTraverser = new NodeTraverser();
            $nodeTraverser->addVisitor($visitor);
            $nodeTraverser->traverse($nodes);
            $nameContext = $visitor->getNameContext();
        } else {
            $nameContext = $this->findDocCommentInIndex($uri, $docComment);
        }

        $nameContext = $nameContext ?? new NameContext();
        $nameContext->class = $className ? '\\' . $className : null;

        return $this->getResolvedPhpDocForNameContext($docComment, $nameContext);
    }

    /**
     * @return NameContext|null
     */
    private function findDocCommentInIndex(Uri $uri, string $docComment)
    {
        if ($this->document === null) {
            throw new ShouldNotHappenException();
        }

        /** @var ClassLike[] $classes */
        $classes = $this->syncAsync->callAsync(
            $this->reflectionProvider->getClassesFromUri($this->document, $uri)
        );

        foreach ($classes as $class) {
            if ($class->docComment === $docComment) {
                return $class->nameContext;
            }

            foreach (array_merge($class->methods, $class->properties, $class->consts) as $member) {
                if ($member->docComment === $docComment) {
                    return $member->nameContext;
                }
            }
        }

        /** @var Function_[] $functions */
        $functions = $this->syncAsync->callAsync(
            $this->reflectionProvider->getFunctionsFromUri($this->document, $uri)
        );

        foreach ($functions as $function) {
            if ($function->docComment === $docComment) {
                return $function->nameContext;
            }
        }

        /** @var Const_[] $consts */
        $consts = $this->syncAsync->callAsync(
            $this->reflectionProvider->getConstsFromUri($this->document, $uri)
        );

        foreach ($consts as $const) {
            if ($const->docComment === $docComment) {
                return $const->nameContext;
            }
        }

        return null;
    }

    public function getResolvedPhpDocForReflectionElement(Element $element): ResolvedPhpDocBlock
    {
        return $this->getResolvedPhpDocForNameContext($element->docComment ?: '/** */', $element->nameContext);
    }

    public function getResolvedPhpDocForNameContext(string $docComment, NameContext $context): ResolvedPhpDocBlock
    {
        $nameScope = new NameScope(
            ltrim($context->namespace, '\\') ?: null,
            array_map(function (string $name) {
                return ltrim($name, '\\');
            }, $context->uses),
            ltrim((string)$context->class, '\\') ?: null
        );

        return $this->phpDocStringResolver->resolve($docComment, $nameScope);
    }
}
