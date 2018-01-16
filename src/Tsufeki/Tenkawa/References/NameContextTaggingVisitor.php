<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\Comment;
use PhpParser\Node;
use Tsufeki\Tenkawa\Reflection\NameContextVisitor;

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
            $node->setAttribute('nameContext', $this->nameContext);
        }

        return null;
    }
}
