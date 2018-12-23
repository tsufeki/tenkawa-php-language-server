<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\SignatureHelp;

use PhpParser\Comment;
use PhpParser\Node;
use Tsufeki\Tenkawa\Php\Feature\Symbol;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\SignatureHelp;

interface SignatureFinder
{
    /**
     * @param Node\Arg[]            $args
     * @param (Node|Comment)[]|null $nodePath
     *
     * @resolve SignatureHelp|null
     */
    public function findSignature(Symbol $symbol, array $args, int $argIndex, ?array $nodePath): \Generator;
}
