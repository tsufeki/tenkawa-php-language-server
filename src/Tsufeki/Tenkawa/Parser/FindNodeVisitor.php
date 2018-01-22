<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Parser;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Utils\PositionUtils;

class FindNodeVisitor extends NodeVisitorAbstract
{
    /**
     * @var int
     */
    private $offset;

    /**
     * @var (Node|Comment)[]
     */
    private $nodes = [];

    /**
     * @var int
     */
    private $rightEndAdjustment;

    /**
     * @var int
     */
    private $depth = 0;

    /**
     * @param bool $stickToRightEnd If true, positions just after a node are
     *                              counted as belonging to it.
     */
    public function __construct(Document $document, Position $position, bool $stickToRightEnd = false)
    {
        $this->offset = PositionUtils::offsetFromPosition($position, $document);
        $this->rightEndAdjustment = $stickToRightEnd ? 1 : 0;
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

        if ($node->getAttribute('startFilePos') <= $this->offset
            && $this->offset <= $node->getAttribute('endFilePos') + $this->rightEndAdjustment
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

    /**
     * @return (Node|Comment)[]
     */
    public function getNodes(): array
    {
        return array_reverse($this->nodes);
    }
}
