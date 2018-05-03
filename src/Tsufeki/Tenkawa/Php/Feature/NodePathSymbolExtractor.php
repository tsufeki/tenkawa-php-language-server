<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use PhpParser\Comment;
use PhpParser\Node;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;

interface NodePathSymbolExtractor
{
    /**
     * @param (Node|Comment)[] $nodes
     *
     * @resolve Symbol|null
     */
    public function getSymbolAt(Document $document, Position $position, array $nodes): \Generator;
}
