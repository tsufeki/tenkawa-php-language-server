<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Comment;
use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\Node as PhpDocNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use Tsufeki\Tenkawa\Php\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class DocCommentSymbolExtractor implements NodePathSymbolExtractor
{
    /**
     * @var Lexer
     */
    private $lexer;

    /**
     * @var PhpDocParser
     */
    private $phpDocParser;

    /**
     * @var ClassResolver
     */
    private $classResolver;

    public function __construct(
        Lexer $lexer,
        PhpDocParser $phpDocParser,
        ClassResolver $classResolver
    ) {
        $this->lexer = $lexer;
        $this->phpDocParser = $phpDocParser;
        $this->classResolver = $classResolver;
    }

    /**
     * @param Node|Comment $node
     */
    public function filterNode($node): bool
    {
        return $node instanceof Comment\Doc;
    }

    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve Symbol|null
     */
    public function getSymbolAt(Document $document, Position $position, array $nodes): \Generator
    {
        if (count($nodes) < 2 || !($nodes[0] instanceof Comment\Doc) || !($nodes[1] instanceof Node)) {
            return null;
        }

        $comment = $nodes[0];
        $node = $nodes[1];

        $offset = PositionUtils::offsetFromPosition($position, $document);
        $tokens = $this->lexer->tokenize($comment->getText());

        $currentOffset = $comment->getFilePos();
        $length = 0;
        $name = null;
        foreach ($tokens as [$value, $type]) {
            $length = strlen($value);
            if ($currentOffset <= $offset && $offset <= $currentOffset + $length) {
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

        return yield $this->makeSymbol(
            $name,
            $node,
            $document,
            new Range(
                PositionUtils::positionFromOffset($currentOffset, $document),
                PositionUtils::positionFromOffset($currentOffset + $length, $document)
            )
        );
        yield;
    }

    /**
     * @param (Node|Comment)[][] $nodes
     *
     * @resolve Symbol[]
     */
    public function getSymbolsInRange(Document $document, Range $range, array $nodes, ?string $symbolClass = null): \Generator
    {
        if ($symbolClass !== null && $symbolClass !== GlobalSymbol::class) {
            return [];
        }

        $symbols = [];
        foreach ($nodes as $nodePath) {
            $comment = $nodePath[0] ?? null;
            $node = $nodePath[1] ?? null;

            if ($comment instanceof Comment\Doc && $node instanceof Node) {
                $tokens = new TokenIterator($this->lexer->tokenize($comment->getText()));
                $phpDocNode = $this->phpDocParser->parse($tokens);
                $tokens->consumeTokenType(Lexer::TOKEN_END);

                foreach ($this->extractClasses($phpDocNode) as $name) {
                    $symbols[] = yield $this->makeSymbol(
                        $name,
                        $node,
                        $document,
                        // TODO more precise range
                        new Range(
                            PositionUtils::positionFromOffset($comment->getFilePos(), $document),
                            PositionUtils::positionFromOffset($comment->getFilePos() + strlen($comment->getText()), $document)
                        )
                    );
                }
            }
        }

        return $symbols;
        yield;
    }

    /**
     * @param PhpDocNode|PhpDocNode[]|mixed $phpDocNode
     *
     * @return string[]
     */
    private function extractClasses($phpDocNode): array
    {
        if ($phpDocNode instanceof IdentifierTypeNode) {
            return [$phpDocNode->name];
        }

        if ($phpDocNode instanceof PhpDocNode) {
            $phpDocNode = get_object_vars($phpDocNode);
        }

        if (is_array($phpDocNode)) {
            $result = [];
            foreach ($phpDocNode as $child) {
                $result = array_merge($result, $this->extractClasses($child));
            }

            return $result;
        }

        return [];
    }

    /**
     * @resolve GlobalSymbol
     */
    private function makeSymbol(string $className, Node $node, Document $document, Range $range): \Generator
    {
        /** @var NameContext $nameContext */
        $nameContext = $node->getAttribute('nameContext') ?? new NameContext();
        $originalName = $className;
        $lowercaseName = strtolower($className);
        if ($lowercaseName === 'self' || $lowercaseName === 'static') {
            $className = $nameContext->class ?? $className;
        } elseif ($lowercaseName === 'parent') {
            if ($nameContext->class !== null) {
                $className = (yield $this->classResolver->getParent($nameContext->class, $document)) ?? $className;
            }
        } else {
            $className = $nameContext->resolveClass($className);
        }

        $symbol = new GlobalSymbol();
        $symbol->kind = GlobalSymbol::CLASS_;
        $symbol->nameContext = $nameContext;
        $symbol->referencedNames = [$className];
        $symbol->originalName = $originalName;
        $symbol->document = $document;
        $symbol->range = $range;

        return $symbol;
    }
}
