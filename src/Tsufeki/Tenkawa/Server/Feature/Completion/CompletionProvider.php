<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Completion;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;

interface CompletionProvider
{
    /**
     * @resolve CompletionList
     */
    public function getCompletions(
        Document $document,
        Position $position,
        CompletionContext $context = null
    ): \Generator;

    /**
     * @return string[]
     */
    public function getTriggerCharacters(): array;
}
