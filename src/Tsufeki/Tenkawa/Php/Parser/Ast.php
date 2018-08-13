<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Parser;

use PhpParser\Error;
use PhpParser\Node;

class Ast
{
    /**
     * @var Node[]
     */
    public $nodes = [];

    /**
     * @var Error[]
     */
    public $errors = [];
}
