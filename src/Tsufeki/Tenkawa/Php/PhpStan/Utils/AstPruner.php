<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan\Utils;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt;

class AstPruner
{
    /**
     * @param (Node|Comment)[] $nodePath
     * @param Node[]           $ast
     *
     * @resolve Node[]
     */
    public function pruneToCurrentFunction(array $nodePath, array $ast): \Generator
    {
        /** @var Stmt\Namespace_|null $namespace */
        $namespace = null;
        /** @var Stmt\ClassLike|null $class */
        $class = null;
        /** @var Stmt\ClassMethod|Stmt\Property|Stmt\ClassConst|Stmt\Function_|null $function */
        $function = null;

        foreach ($nodePath as $node) {
            if ($function === null &&
                $class === null && (
                $node instanceof Stmt\ClassMethod ||
                $node instanceof Stmt\Property ||
                $node instanceof Stmt\ClassConst ||
                $node instanceof Stmt\Function_
            )) {
                $function = $node;
            }

            if ($class === null && $node instanceof Stmt\ClassLike) {
                $class = $node;
            }

            if ($namespace === null && $node instanceof Stmt\Namespace_) {
                $namespace = $node;
            }
        }

        if ($function === null || (($class === null) !== ($function instanceof Stmt\Function_))) {
            return $ast;
        }

        $node = $function;

        if ($class !== null) {
            $classCopy = clone $class;
            $classCopy->stmts = [$node];
            $node = $classCopy;
        }

        if ($namespace !== null) {
            $namespaceCopy = clone $namespace;
            $namespaceCopy->stmts = $this->filterStatements($namespaceCopy->stmts, $node);
            $namespaceCopy->stmts[] = $node;
            $node = $namespaceCopy;
        }

        $result = $this->filterStatements($ast, $node);
        $result[] = $node;

        return $result;
        yield;
    }

    /**
     * @param Node[] $stmts
     *
     * @return Stmt[]
     */
    private function filterStatements(array $stmts, Stmt $beforeNode): array
    {
        $result = [];
        foreach ($stmts as $stmt) {
            if ($stmt === $beforeNode) {
                break;
            }

            if ($stmt instanceof Stmt\Declare_ ||
                $stmt instanceof Stmt\Use_ ||
                $stmt instanceof Stmt\GroupUse
            ) {
                $result[] = $stmt;
            }
        }

        return $result;
    }
}
