<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\References;

use PhpParser\Comment;
use PhpParser\Node;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Protocol\Common\Position;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionContext;
use Tsufeki\Tenkawa\Protocol\Server\TextDocument\CompletionList;

interface CompletionProvider
{
    /**
     * @param (Node|Comment)[] $nodes Nodes at $position, from the closest to the root.
     *
     * @resolve CompletionList
     */
    public function getCompletions(
        Document $document,
        Position $position,
        CompletionContext $context = null,
        array $nodes
    ): \Generator;

    /**
     * @return string[]
     */
    public function getTriggerCharacters(): array;
}
