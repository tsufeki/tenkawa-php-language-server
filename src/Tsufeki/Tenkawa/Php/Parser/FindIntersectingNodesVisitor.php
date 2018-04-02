<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Parser;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Range;
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
     * @var (Node|Comment)[]
     */
    private $nodes = [];

    public function __construct(Document $document, Range $range)
    {
        $this->offsetA = PositionUtils::offsetFromPosition($range->start, $document);
        $this->offsetB = PositionUtils::offsetFromPosition($range->end, $document);
    }

    public function enterNode(Node $node)
    {
        /** @var Comment $comment */
        foreach ($node->getAttribute('comments') ?? [] as $comment) {
            if ($this->intersects(
                $comment->getFilePos(),
                $comment->getFilePos() + strlen($comment->getText())
            )) {
                $this->nodes[] = $comment;

                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }
        }

        if ($this->intersects(
            $node->getAttribute('startFilePos'),
            $node->getAttribute('endFilePos') + 1
        )) {
            $this->nodes[] = $node;
        } else {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }
    }

    private function intersects(int $otherOffsetA, int $otherOffsetB): bool
    {
        return $this->offsetB > $otherOffsetA && $this->offsetA < $otherOffsetB;
    }

    /**
     * @return (Node|Comment)[]
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }
}
