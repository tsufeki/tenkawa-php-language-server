<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\NodeFinder;

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
    public function getNodePath(Document $document, Position $position, bool $withRightWhitespace = false): \Generator
    {
        $ast = yield $this->parser->parse($document);

        $visitor = new FindNodeVisitor($document, $position, true, $withRightWhitespace, $ast->tokens);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);
        $nodes = $visitor->getNodes();

        $nodeStorage = new \SplObjectStorage();
        foreach ($nodes as $node) {
            $nodeStorage->attach($node);
        }

        $visitor = new NameContextTaggingVisitor($nodeStorage, $document->getUri());
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);

        return $nodes;
    }

    /**
     * @param callable $filter (Node|Comment) -> bool
     *
     * @resolve (Node|Comment)[]
     */
    public function getNodePathsIntersectingWithRange(Document $document, Range $range, callable $filter): \Generator
    {
        $ast = yield $this->parser->parse($document);

        $visitor = new FindIntersectingNodesVisitor($document, $range, $filter);
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);
        $nodePaths = $visitor->getNodePaths();

        $nodeStorage = new \SplObjectStorage();
        foreach ($nodePaths as $nodes) {
            foreach ($nodes as $node) {
                $nodeStorage->attach($node);
            }
        }

        $visitor = new NameContextTaggingVisitor($nodeStorage, $document->getUri());
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($visitor);
        $nodeTraverser->traverse($ast->nodes);

        return $nodePaths;
    }
}
