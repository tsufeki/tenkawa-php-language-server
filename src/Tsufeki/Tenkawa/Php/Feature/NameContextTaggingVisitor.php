<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Node;
use Tsufeki\Tenkawa\Php\Reflection\NameContextVisitor;

class NameContextTaggingVisitor extends NameContextVisitor
{
    /**
     * @var \SplObjectStorage
     */
    private $nodesToTag;

    /**
     * @param \SplObjectStorage $nodesToTag of Node
     */
    public function __construct(\SplObjectStorage $nodesToTag)
    {
        parent::__construct();
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
