<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\PhpStan\Utils;

use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;

class ErrorTolerantPrettyPrinter extends Standard
{
    protected function pExpr_Error(Expr\Error $node)
    {
        return '';
    }
}
