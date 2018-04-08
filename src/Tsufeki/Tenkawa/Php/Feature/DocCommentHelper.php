<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Comment;
use PhpParser\Node;
use PHPStan\PhpDocParser\Lexer\Lexer;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class DocCommentHelper
{
    /**
     * @var Lexer
     */
    private $lexer;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    public function __construct(Lexer $lexer, ReflectionProvider $reflectionProvider)
    {
        $this->lexer = $lexer;
        $this->reflectionProvider = $reflectionProvider;
    }

    public function getReferencedClass(Comment $comment, Node $node, int $fileOffset)
    {
        $tokens = $this->lexer->tokenize($comment->getText());
        $offset = $fileOffset - $comment->getFilePos();
        $currentOffset = 0;
        $name = null;
        foreach ($tokens as list($value, $type)) {
            $length = strlen($value);
            if ($currentOffset <= $offset && $offset < $currentOffset + $length) {
                if ($type === Lexer::TOKEN_IDENTIFIER) {
                    $name = $value;
                }
                break;
            }

            $currentOffset += $length;
        }

        if (!$name) {
            return null;
        }

        /** @var NameContext $nameContext */
        $nameContext = $node->getAttribute('nameContext') ?? new NameContext();

        return $nameContext->resolveClass($name);
    }

    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve Element[]
     */
    public function getReflectionFromNodePath(array $nodes, Document $document, Position $position): \Generator
    {
        $elements = [];

        if (count($nodes) >= 2 && $nodes[0] instanceof Comment && $nodes[1] instanceof Node) {
            $coroutines = [];
            $offset = PositionUtils::offsetFromPosition($position, $document);

            $className = $this->getReferencedClass($nodes[0], $nodes[1], $offset);
            if ($className !== null) {
                $coroutines[] = $this->reflectionProvider->getClass($document, $className);
            }

            $elements = $coroutines ? array_merge(...yield $coroutines) : [];
        }

        return $elements;
    }
}
