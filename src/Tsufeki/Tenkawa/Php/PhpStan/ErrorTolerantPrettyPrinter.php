<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan;

use PhpParser\Node\Expr;
use PhpParser\Node\Param;
use PhpParser\PrettyPrinter\Standard;

class ErrorTolerantPrettyPrinter extends Standard
{
    protected function pExpr_Error(Expr\Error $node)
    {
        return '';
    }

    protected function pParam(Param $node)
    {
        return ($node->type ? $this->pType($node->type) . ' ' : '')
            . ($node->byRef ? '&' : '')
            . ($node->variadic ? '...' : '')
            . '$' . (is_string($node->name) ? $node->name : '')
            . ($node->default ? ' = ' . $this->p($node->default) : '');
    }
}
