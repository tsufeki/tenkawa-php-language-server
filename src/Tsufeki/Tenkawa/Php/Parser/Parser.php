<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Parser;

use PhpParser\Node\Expr;
use Tsufeki\Tenkawa\Server\Document\Document;

interface Parser
{
    /**
     * @resolve Ast
     */
    public function parse(Document $document): \Generator;

    /**
     * @resolve Expr
     */
    public function parseExpr(string $expr): \Generator;
}
