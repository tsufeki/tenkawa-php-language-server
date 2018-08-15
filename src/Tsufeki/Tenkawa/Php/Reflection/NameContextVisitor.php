<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;
use Tsufeki\Tenkawa\Server\Uri;

class NameContextVisitor extends NodeVisitorAbstract
{
    /**
     * @var NameContext
     */
    protected $nameContext;

    /**
     * @var (string|null)[]
     */
    private $classStack = [];

    /**
     * @var Uri
     */
    private $uri;

    public function __construct(Uri $uri)
    {
        $this->nameContext = new NameContext();
        $this->uri = $uri;
    }

    private function addUse(Stmt\UseUse $use, int $type, ?Name $prefix): void
    {
        $type |= $use->type;
        $name = '\\' . ($prefix ? $prefix->toString() . '\\' : '') . $use->name->toString();
        $alias = $use->getAlias()->name;

        if ($type === Stmt\Use_::TYPE_FUNCTION) {
            $this->nameContext->functionUses[$alias] = $name;
        } elseif ($type === Stmt\Use_::TYPE_CONSTANT) {
            $this->nameContext->constUses[$alias] = $name;
        } else {
            $this->nameContext->uses[$alias] = $name;
        }
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->nameContext->namespace = isset($node->name) ? '\\' . $node->name->toString() : '\\';
            $this->nameContext->uses = [];
            $this->nameContext->functionUses = [];
            $this->nameContext->constUses = [];
            $this->nameContext->class = null;

            return null;
        }

        if ($node instanceof Stmt\Use_) {
            foreach ($node->uses as $use) {
                $this->addUse($use, $node->type, null);
            }

            return null;
        }

        if ($node instanceof Stmt\GroupUse) {
            foreach ($node->uses as $use) {
                $this->addUse($use, $node->type, $node->prefix);
            }

            return null;
        }

        if ($node instanceof Stmt\ClassLike) {
            $className = null;
            if (isset($node->namespacedName)) {
                $className = "\\$node->namespacedName";
            } elseif ($node->name !== null) {
                $className = "\\$node->name";
            } elseif ($node instanceof Stmt\Class_) {
                $className = NameHelper::getAnonymousClassName($this->uri, $node);
            }

            $this->nameContext->class = $className;
            $this->classStack[] = $className;

            return null;
        }

        if ($node instanceof Stmt\Function_) {
            $this->nameContext->class = null;
            $this->classStack[] = null;

            return null;
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Stmt\ClassLike || $node instanceof Stmt\Function_) {
            array_pop($this->classStack);
            $this->nameContext->class = $this->classStack[count($this->classStack) - 1] ?? null;

            return null;
        }

        return null;
    }
}
