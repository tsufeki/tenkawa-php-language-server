<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Comment;
use PhpParser\Node;
use PHPStan\PhpDocParser\Lexer\Lexer;
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

    public function __construct(Lexer $lexer)
    {
        $this->lexer = $lexer;
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
        foreach ($tokens as list($value, $type)) {
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

        $symbol = new GlobalSymbol();
        $symbol->kind = GlobalSymbol::CLASS_;
        $symbol->nameContext = $node->getAttribute('nameContext') ?? new NameContext();
        $symbol->referencedNames = [$symbol->nameContext->resolveClass($name)];
        $symbol->originalName = $name;
        $symbol->document = $document;
        $symbol->range = new Range(
            PositionUtils::positionFromOffset($currentOffset, $document),
            PositionUtils::positionFromOffset($currentOffset + $length, $document)
        );

        return $symbol;
        yield;
    }

    /**
     * @param (Node|Comment)[][] $nodes
     *
     * @resolve Symbol[]
     */
    public function getSymbolsInRange(Document $document, Range $range, array $nodes, string $symbolClass = null): \Generator
    {
        if ($symbolClass !== null && $symbolClass !== GlobalSymbol::class) {
            return [];
        }

        $symbols = [];
        foreach ($nodes as $nodePath) {
            $comment = $nodePath[0] ?? null;

            // TODO extract all symbols, not just one
            if ($comment instanceof Comment\Doc &&
                $comment->getFilePos() < PositionUtils::offsetFromPosition($range->start, $document)
            ) {
                $symbols[] = yield $this->getSymbolAt($document, $range->start, $nodePath);
            }
        }

        return array_values(array_filter($symbols));
    }
}
