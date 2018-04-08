<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;

class MemberFetch
{
    /**
     * @var Expr
     */
    public $node;

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
