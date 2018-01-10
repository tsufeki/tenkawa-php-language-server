<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use Tsufeki\Tenkawa\Reflection\NameContext;
use Tsufeki\Tenkawa\Reflection\NameContextVisitor;

class PhpDocResolverVisitor extends NameContextVisitor
{
    /**
     * @var string
     */
    private $docCommentNeedle;

    /**
     * @var NameContext|null
     */
    private $foundNameContext;

    public function __construct(string $docCommentNeedle)
    {
        parent::__construct();
        $this->docCommentNeedle = $docCommentNeedle;
    }

    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        $phpDoc = $node->getDocComment();
        if ($phpDoc !== null && $phpDoc->getText() === $this->docCommentNeedle) {
            $this->foundNameContext = clone $this->nameContext;

            return NodeTraverser::STOP_TRAVERSAL;
        }
    }

    /**
     * @return NameContext|null
     */
    public function getNameContext()
    {
        return $this->foundNameContext;
    }
}
