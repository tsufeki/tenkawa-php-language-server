<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\Comment;
use PhpParser\Node;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Location;
use Tsufeki\Tenkawa\Protocol\Common\Position;

interface GoToDefinitionProvider
{
    /**
     * @param (Node|Comment)[] $nodes Nodes at $position, from the closest to the root.
     *
     * @resolve Location[]
     */
    public function getLocations(Document $document, Position $position, array $nodes): \Generator;
}
