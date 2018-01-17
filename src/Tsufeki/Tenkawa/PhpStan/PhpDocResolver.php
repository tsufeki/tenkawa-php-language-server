<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

use PhpParser\NodeTraverser;
use PHPStan\Analyser\NameScope;
use PHPStan\Parser\Parser;
use PHPStan\PhpDoc\PhpDocStringResolver;
use PHPStan\PhpDoc\ResolvedPhpDocBlock;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\FileTypeMapper;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Reflection\Element\Const_;
use Tsufeki\Tenkawa\Reflection\Element\Element;
use Tsufeki\Tenkawa\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Reflection\NameContext;
use Tsufeki\Tenkawa\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Uri;
use Tsufeki\Tenkawa\Utils\SyncAsync;

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

        /** @var (ClassLike|Function_|Const_)[] $elements */
        $elements = $this->syncAsync->callAsync(
            $this->reflectionProvider->getSymbolsFromUri($this->document, $uri)
        );

        foreach ($elements as $element) {
            if ($element->docComment === $docComment) {
                return $element->nameContext;
            }

            if ($element instanceof ClassLike) {
                foreach (array_merge($element->methods, $element->properties, $element->consts) as $subElement) {
                    if ($subElement->docComment === $docComment) {
                        return $subElement->nameContext;
                    }
                }
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
