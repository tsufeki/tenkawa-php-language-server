<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNullNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\MethodTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\DocComment;
use Tsufeki\Tenkawa\Php\Reflection\Element\Param;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedMethod;
use Tsufeki\Tenkawa\Php\Reflection\Resolved\ResolvedProperty;
use Tsufeki\Tenkawa\Server\Document\Document;

class MembersFromAnnotationClassResolverExtension implements ClassResolverExtension
{
    /**
     * @var Lexer
     */
    private $phpDocLexer;

    /**
     * @var PhpDocParser
     */
    private $phpDocParser;

    const ORIGIN = 'annotation';

    public function __construct(Lexer $phpDocLexer, PhpDocParser $phpDocParser)
    {
        $this->phpDocLexer = $phpDocLexer;
        $this->phpDocParser = $phpDocParser;
    }

    public function resolve(ResolvedClassLike $class, Document $document): \Generator
    {
        $docComment = $class->docComment->text ?? null;
        if ($docComment !== null) {
            try {
                $tokens = new TokenIterator($this->phpDocLexer->tokenize($docComment));
                $phpDocNode = $this->phpDocParser->parse($tokens);
                $tokens->consumeTokenType(Lexer::TOKEN_END);

                foreach ($phpDocNode->getPropertyTagValues() as $tag) {
                    $this->createProperty($class, $tag, true, true);
                }

                foreach ($phpDocNode->getPropertyReadTagValues() as $tag) {
                    $this->createProperty($class, $tag, true, false);
                }

                foreach ($phpDocNode->getPropertyWriteTagValues() as $tag) {
                    $this->createProperty($class, $tag, false, true);
                }

                foreach ($phpDocNode->getMethodTagValues() as $tag) {
                    $this->createMethod($class, $tag);
                }
            } catch (ParserException $e) {
            }
        }

        return;
        yield;
    }

    private function createProperty(ResolvedClassLike $class, PropertyTagValueNode $tag, bool $readable, bool $writable): void
    {
        $property = new ResolvedProperty();
        $property->name = substr($tag->propertyName, 1);
        $property->accessibility = ClassLike::M_PUBLIC;
        $property->location = $class->location; // TODO be more precise
        $property->static = false;
        $property->readable = $readable;
        $property->writable = $writable;
        $property->nameContext = $class->nameContext;
        $property->origin = self::ORIGIN;
        $property->docComment = new DocComment();
        $property->docComment->text = "/** @var $tag->type */";

        if (!isset($class->properties[$property->name]) || $class->properties[$property->name]->nameContext->class !== $class->name) {
            $class->properties[$property->name] = $property;
        }
    }

    private function createMethod(ResolvedClassLike $class, MethodTagValueNode $tag): void
    {
        $method = new ResolvedMethod();
        $method->name = $tag->methodName;
        $method->accessibility = ClassLike::M_PUBLIC;
        $method->location = $class->location; // TODO be more precise
        $method->static = $tag->isStatic;
        $method->nameContext = $class->nameContext;
        $method->origin = self::ORIGIN;

        $docComment = "/**\n";
        if ($tag->description) {
            $docComment .= " * $tag->description\n";
        }

        $method->params = [];
        foreach ($tag->parameters as $tagParam) {
            $param = new Param();
            $param->name = substr($tagParam->parameterName, 1);
            $param->byRef = $tagParam->isReference;
            $param->variadic = $tagParam->isVariadic;
            $param->defaultNull = $tagParam->defaultValue instanceof ConstExprNullNode;
            $param->defaultExpression = $tagParam->defaultValue !== null ? (string)$tagParam->defaultValue : null;
            $param->optional = $param->defaultNull || $param->defaultExpression;
            $method->params[] = $param;

            $type = $tagParam->type ? " $tagParam->type" : '';
            $docComment .= " * @param$type \$$param->name\n";
        }

        if ($tag->returnType) {
            $docComment .= " * @return $tag->returnType\n";
        }

        $docComment .= '*/';
        $method->docComment = new DocComment();
        $method->docComment->text = $docComment;

        if (!isset($class->methods[$method->name]) || $class->methods[$method->name]->nameContext->class !== $class->name) {
            $class->methods[$method->name] = $method;
        }
    }
}
