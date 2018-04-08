<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use Tsufeki\Tenkawa\Php\Parser\FindIntersectingNodesVisitor;
use Tsufeki\Tenkawa\Php\Parser\FindNodeVisitor;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;

class NodeFinder
{
    /**
     * @var Parser
     */
    private $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * @resolve (Node|Comment)[] Nodes at $position, from the closest to the root.
     */
    public function getNodePath(Document $document, Position $position): \Generator
    {
        $ast = yield $this->parser->parse($document);

        $visitor = new FindNodeVisitor($document, $position, true);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);
        $nodes = $visitor->getNodes();

        $visitor = new NameContextTaggingVisitor($nodes);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);

        return $nodes;
    }

    /**
     * @resolve (Node|Comment)[]
     */
    public function getNodesIntersectingWithRange(Document $document, Range $range): \Generator
    {
        $ast = yield $this->parser->parse($document);

        $visitor = new FindIntersectingNodesVisitor($document, $range);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);
        $nodes = $visitor->getNodes();

        return $nodes;
    }
}
