<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use Tsufeki\Tenkawa\Protocol\Common\Range;

class MemberFetch
{
    /**
     * @var Name|Expr
     */
    public $leftNode;

    /**
     * @var string|Expr
     */
    public $name;

    /**
     * @var Range
     */
    public $nameRange;
}
