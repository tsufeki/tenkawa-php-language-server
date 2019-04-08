<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\NodeFinder;

use PhpParser\Node;
use Tsufeki\Tenkawa\Php\Reflection\NameContextVisitor;
use Tsufeki\Tenkawa\Server\Uri;

class NameContextTaggingVisitor extends NameContextVisitor
{
    /**
     * @var \SplObjectStorage
     */
    private $nodesToTag;

    /**
     * @param \SplObjectStorage $nodesToTag of Node
     */
    public function __construct(\SplObjectStorage $nodesToTag, Uri $uri)
    {
        parent::__construct($uri);
        $this->nodesToTag = $nodesToTag;
    }

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($this->nodesToTag->contains($node)) {
            $node->setAttribute('nameContext', clone $this->nameContext);
        }

        return null;
    }
}
