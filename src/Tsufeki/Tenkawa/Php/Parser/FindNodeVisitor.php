<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Parser;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class FindNodeVisitor extends NodeVisitorAbstract
{
    /**
     * @var int
     */
    private $offset;

    /**
     * @var int
     */
    private $rightEndAdjustment;

    /**
     * @var bool
     */
    private $withRightWhitespace = false;

    /**
     * @var (Node|Comment)[]
     */
    private $nodes = [];

    /**
     * @var int
     */
    private $depth = 0;

    /**
     * @var array
     */
    private $tokens;

    /**
     * @param bool $stickToRightEnd If true, positions just after a node are
     *                              counted as belonging to it.
     */
    public function __construct(
        Document $document,
        Position $position,
        bool $stickToRightEnd = false,
        bool $withRightWhitespace = false,
        array $tokens = []
    ) {
        $this->offset = PositionUtils::offsetFromPosition($position, $document);
        $this->rightEndAdjustment = $stickToRightEnd ? 1 : 0;
        $this->withRightWhitespace = $withRightWhitespace;
        $this->tokens = $tokens;
    }

    public function enterNode(Node $node)
    {
        $this->depth++;

        /** @var Comment $comment */
        foreach ($node->getAttribute('comments') ?? [] as $comment) {
            if ($comment->getFilePos() <= $this->offset
                && $this->offset < $comment->getFilePos() + strlen($comment->getText())
            ) {
                $this->nodes[] = $node;
                $this->nodes[] = $comment;

                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }
        }

        $rightAdjustment = max($this->rightEndAdjustment, $this->countWhitespace($node));
        if ($node->getAttribute('startFilePos') <= $this->offset
            && $this->offset <= $node->getAttribute('endFilePos') + $rightAdjustment
            && $this->depth > count($this->nodes)
        ) {
            $this->nodes[] = $node;
        } else {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }
    }

    public function leaveNode(Node $node)
    {
        $this->depth--;
    }

    private function countWhitespace(Node $node): int
    {
        if (!$this->withRightWhitespace) {
            return 0;
        }

        $tokenIter = new TokenIterator($this->tokens, $node->getAttribute('endTokenPos') + 1, PHP_INT_MAX);
        $tokenIter->eatWhitespace();

        return $tokenIter->getOffset();
    }

    /**
     * @return (Node|Comment)[]
     */
    public function getNodes(): array
    {
        return array_reverse($this->nodes);
    }
}
