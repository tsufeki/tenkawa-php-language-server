<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use Tsufeki\Tenkawa\Php\Reflection\Element\ClassLike;
use Tsufeki\Tenkawa\Php\Reflection\Element\DocComment;
use Tsufeki\Tenkawa\Php\Reflection\Element\Property;
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
            } catch (ParserException $e) {
            }
        }

        return;
        yield;
    }

    private function createProperty(ResolvedClassLike $class, PropertyTagValueNode $tag, bool $readable, bool $writable)
    {
        $property = new Property();
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
}
