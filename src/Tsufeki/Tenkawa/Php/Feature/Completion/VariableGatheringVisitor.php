<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Completion;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Tsufeki\Tenkawa\Php\TypeInference\Type;

class VariableGatheringVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<string,Type|null>
     */
    private $variables;

    /**
     * @param array<string,Type|null> $initialVariables
     */
    public function __construct(array $initialVariables = [])
    {
        $this->variables = $initialVariables;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Expr\Variable && is_string($node->name)) {
            $this->variables[$node->name] = $node->getAttribute('type');

            return null;
        }

        if ($node instanceof FunctionLike || $node instanceof Stmt\ClassLike) {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        return null;
    }

    /**
     * @return array<string,Type|null>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }
}
