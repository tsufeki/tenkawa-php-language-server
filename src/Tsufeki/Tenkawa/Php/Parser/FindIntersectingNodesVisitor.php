<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Parser;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Utils\PositionUtils;

class FindIntersectingNodesVisitor extends NodeVisitorAbstract
{
    /**
     * @var int
     */
    private $offsetA;

    /**
     * @var int
     */
    private $offsetB;

    /**
     * @var callable
     */
    private $filter;

    /**
     * @var (Node|Comment)[]
     */
    private $nodeStack = [];

    /**
     * @var (Node|Comment)[][]
     */
    private $nodePaths = [];

    public function __construct(Document $document, Range $range, callable $filter)
    {
        $this->offsetA = PositionUtils::offsetFromPosition($range->start, $document);
        $this->offsetB = PositionUtils::offsetFromPosition($range->end, $document);
        $this->filter = $filter;
    }

    public function enterNode(Node $node)
    {
        $this->nodeStack[] = $node;

        /** @var Comment $comment */
        foreach ($node->getAttribute('comments') ?? [] as $comment) {
            if (
                $this->intersects(
                    $comment->getFilePos(),
                    $comment->getFilePos() + strlen($comment->getText())
                ) &&
                ($this->filter)($comment)
            ) {
                $this->nodeStack[] = $comment;
                $this->nodePaths[] = array_reverse($this->nodeStack);
                array_pop($this->nodeStack);
            }
        }

        if (!$this->intersects(
            $node->getAttribute('startFilePos'),
            $node->getAttribute('endFilePos') + 1
        )) {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if (($this->filter)($node)) {
            $this->nodePaths[] = array_reverse($this->nodeStack);
        }
    }

    public function leaveNode(Node $node)
    {
        array_pop($this->nodeStack);
    }

    private function intersects(int $otherOffsetA, int $otherOffsetB): bool
    {
        return $this->offsetB > $otherOffsetA && $this->offsetA < $otherOffsetB;
    }

    /**
     * @return (Node|Comment)[][]
     */
    public function getNodePaths(): array
    {
        return $this->nodePaths;
    }
}
