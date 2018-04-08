<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Comment;
use PhpParser\Node;
use Tsufeki\Tenkawa\Php\Reflection\NameContextVisitor;

class NameContextTaggingVisitor extends NameContextVisitor
{
    /**
     * @var (Node|Comment)[]
     */
    private $nodesToTag = [];

    /**
     * @param (Node|Comment)[] $nodesToTag
     */
    public function __construct(array $nodesToTag)
    {
        parent::__construct();
        $this->nodesToTag = $nodesToTag;
    }

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if (in_array($node, $this->nodesToTag, true)) {
            $node->setAttribute('nameContext', clone $this->nameContext);
        }

        return null;
    }
}
