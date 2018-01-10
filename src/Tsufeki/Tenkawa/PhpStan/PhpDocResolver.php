<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

use PhpParser\NodeTraverser;
use PHPStan\Analyser\NameScope;
use PHPStan\Parser\Parser;
use PHPStan\PhpDoc\PhpDocStringResolver;
use PHPStan\PhpDoc\ResolvedPhpDocBlock;
use PHPStan\Type\FileTypeMapper;
use Tsufeki\Tenkawa\Reflection\Element\Element;
use Tsufeki\Tenkawa\Reflection\NameContext;

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

    public function __construct(
        Parser $phpParser,
        PhpDocStringResolver $phpDocStringResolver
    ) {
        $this->phpParser = $phpParser;
        $this->phpDocStringResolver = $phpDocStringResolver;
    }

    public function getResolvedPhpDoc(string $filename, string $className = null, string $docComment): ResolvedPhpDocBlock
    {
        $nodes = $this->phpParser->parseFile($filename);
        $visitor = new PhpDocResolverVisitor($docComment);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($nodes);
        $nameContext = $visitor->getNameContext() ?? new NameContext();
        $nameContext->class = $className ? '\\' . $className : null;

        return $this->getResolvedPhpDocForNameContext($docComment, $nameContext);
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
            ltrim($context->class, '\\') ?: null
        );

        return $this->phpDocStringResolver->resolve($docComment, $nameScope);
    }
}
