<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\MagicConst;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Utils\Cache;
use Tsufeki\Tenkawa\Server\Utils\InfiniteRecursionMarker;

class ConstExprEvaluation
{
    /**
     * @var Parser
     */
    private $parser;

    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    /**
     * @var ClassResolver
     */
    private $classResolver;

    /**
     * @var Document
     */
    private $document;

    /**
     * @var Cache
     */
    private $cache;

    public function __construct(
        Parser $parser,
        ReflectionProvider $reflectionProvider,
        ClassResolver $classResolver,
        Document $document
    ) {
        $this->parser = $parser;
        $this->reflectionProvider = $reflectionProvider;
        $this->classResolver = $classResolver;
        $this->document = $document;
        $this->cache = new Cache();
    }

    /**
     * @resolve mixed
     */
    public function evaluate(string $expr, NameContext $nameContext): \Generator
    {
        switch (strtolower($expr)) {
            case 'true':
            case '\\true':
                return true;
            case 'false':
            case '\\false':
                return false;
            case 'null':
            case '\\null':
                return null;
        }

        switch ($expr) {
            case 'INF':
            case '\\INF':
                return INF;
            case 'NAN':
            case '\\NAN':
                return NAN;
        }

        $node = yield $this->parser->parseExpr($expr);

        return yield $this->evaluateNode($node, $nameContext);
    }

    /**
     * @resolve mixed
     */
    public function getConstValue(Element\Const_ $const): \Generator
    {
        $key = $const->name;
        if ($const->nameContext && $const->nameContext->class) {
            $key = "{$const->nameContext->class}\\$key";
        }

        $value = $this->cache->get($key);
        if ($value === InfiniteRecursionMarker::get()) {
            return null;
        }
        if ($value !== null) {
            return $value;
        }
        $this->cache->set($key, InfiniteRecursionMarker::get());

        $value = yield $this->evaluate($const->valueExpression ?? '', $const->nameContext);
        $this->cache->set($key, $value);

        return $value;
    }

    private function evaluateNode(Expr $expr, NameContext $nameContext): \Generator
    {
        try {
            if ($expr instanceof Scalar\DNumber || $expr instanceof Scalar\LNumber || $expr instanceof Scalar\String_) {
                return $expr->value;
            }

            // TODO real value from $nameContext?
            if ($expr instanceof MagicConst\Line) {
                return 1;
            }
            if ($expr instanceof MagicConst) {
                return $expr->getName();
            }

            if ($expr instanceof BinaryOp) {
                $left = yield $this->evaluateNode($expr->left, $nameContext);
                $right = yield $this->evaluateNode($expr->right, $nameContext);

                if ($expr instanceof BinaryOp\BitwiseAnd) {
                    return $left & $right;
                }
                if ($expr instanceof BinaryOp\BitwiseOr) {
                    return $left | $right;
                }
                if ($expr instanceof BinaryOp\BitwiseXor) {
                    return $left ^ $right;
                }
                if ($expr instanceof BinaryOp\BooleanAnd) {
                    return $left && $right;
                }
                if ($expr instanceof BinaryOp\BooleanOr) {
                    return $left || $right;
                }
                if ($expr instanceof BinaryOp\Coalesce) {
                    return $left ?? $right;
                }
                if ($expr instanceof BinaryOp\Concat) {
                    return $left . $right;
                }
                if ($expr instanceof BinaryOp\Div) {
                    return $left / $right;
                }
                if ($expr instanceof BinaryOp\Equal) {
                    return $left == $right;
                }
                if ($expr instanceof BinaryOp\Greater) {
                    return $left > $right;
                }
                if ($expr instanceof BinaryOp\GreaterOrEqual) {
                    return $left >= $right;
                }
                if ($expr instanceof BinaryOp\Identical) {
                    return $left === $right;
                }
                if ($expr instanceof BinaryOp\LogicalAnd) {
                    return $left and $right;
                }
                if ($expr instanceof BinaryOp\LogicalOr) {
                    return $left or $right;
                }
                if ($expr instanceof BinaryOp\LogicalXor) {
                    return $left xor $right;
                }
                if ($expr instanceof BinaryOp\Minus) {
                    return $left - $right;
                }
                if ($expr instanceof BinaryOp\Mod) {
                    return $left % $right;
                }
                if ($expr instanceof BinaryOp\Mul) {
                    return $left * $right;
                }
                if ($expr instanceof BinaryOp\NotEqual) {
                    return $left != $right;
                }
                if ($expr instanceof BinaryOp\NotIdentical) {
                    return $left !== $right;
                }
                if ($expr instanceof BinaryOp\Plus) {
                    return $left + $right;
                }
                if ($expr instanceof BinaryOp\Pow) {
                    return $left ** $right;
                }
                if ($expr instanceof BinaryOp\ShiftLeft) {
                    return $left << $right;
                }
                if ($expr instanceof BinaryOp\ShiftRight) {
                    return $left >> $right;
                }
                if ($expr instanceof BinaryOp\Smaller) {
                    return $left < $right;
                }
                if ($expr instanceof BinaryOp\SmallerOrEqual) {
                    return $left <= $right;
                }
                if ($expr instanceof BinaryOp\Spaceship) {
                    return $left <=> $right;
                }

                return null;
            }

            if ($expr instanceof Expr\ArrayDimFetch && $expr->dim !== null) {
                $var = yield $this->evaluateNode($expr->var, $nameContext);
                $dim = yield $this->evaluateNode($expr->dim, $nameContext);

                return $var[$dim];
            }

            if ($expr instanceof Expr\Array_) {
                $array = [];
                foreach ($expr->items as $item) {
                    $value = yield $this->evaluateNode($item->value, $nameContext);
                    if ($item->key !== null) {
                        $key = yield $this->evaluateNode($item->key, $nameContext);
                        $array[$key] = $value;
                    } else {
                        $array[] = $value;
                    }
                }

                return $array;
            }

            if ($expr instanceof Expr\BitwiseNot) {
                $value = yield $this->evaluateNode($expr->expr, $nameContext);

                return ~$value;
            }

            if ($expr instanceof Expr\BooleanNot) {
                $value = yield $this->evaluateNode($expr->expr, $nameContext);

                return !$value;
            }

            if ($expr instanceof Expr\ClassConstFetch && $expr->class instanceof Name && is_string($expr->name)) {
                $class = (string)$expr->class;
                $parent = strtolower($class) === 'parent';
                if (in_array(strtolower($class), ['self', 'parent', 'static'], true) && $nameContext->class) {
                    $class = $nameContext->class;
                }
                $class = '\\' . $class;

                /** @var ResolvedClassLike|null $resolvedClass */
                $resolvedClass = yield $this->classResolver->resolve($class, $this->document);
                if ($parent && $resolvedClass !== null) {
                    $resolvedClass = $resolvedClass->parentClass;
                }
                if ($resolvedClass === null) {
                    return null;
                }

                $const = $resolvedClass->consts[$expr->name] ?? null;

                return $const === null ? null : yield $this->getConstValue($const);
            }

            if ($expr instanceof Expr\ConstFetch) {
                $consts = [];
                if (!($expr->name instanceof Name\FullyQualified)) {
                    $consts = yield $this->reflectionProvider->getConst($this->document, $nameContext->namespace . '\\' . (string)$expr->name);
                }
                if (empty($consts)) {
                    $consts = yield $this->reflectionProvider->getConst($this->document, '\\' . (string)$expr->name);
                }
                if (empty($consts)) {
                    return null;
                }

                return yield $this->getConstValue($consts[0]);
            }

            if ($expr instanceof Expr\Ternary) {
                $cond = yield $this->evaluateNode($expr->cond, $nameContext);
                $if = $expr->if === null ? $cond : yield $this->evaluateNode($expr->if, $nameContext);
                $else = yield $this->evaluateNode($expr->else, $nameContext);

                return $cond ? $if : $else;
            }

            if ($expr instanceof Expr\UnaryMinus) {
                $value = yield $this->evaluateNode($expr->expr, $nameContext);

                return -$value;
            }

            if ($expr instanceof Expr\UnaryPlus) {
                $value = yield $this->evaluateNode($expr->expr, $nameContext);

                return +$value;
            }
        } catch (\Error $e) {
        }

        return null;
    }
}
