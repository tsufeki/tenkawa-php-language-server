<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\Comment;
use PhpParser\Node;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\Hover;

interface HoverProvider
{
    /**
     * @param (Node|Comment)[] $nodes Nodes at $position, from the closest to the root.
     *
     * @resolve Hover|null
     */
    public function getHover(Document $document, Position $position, array $nodes): \Generator;
}
