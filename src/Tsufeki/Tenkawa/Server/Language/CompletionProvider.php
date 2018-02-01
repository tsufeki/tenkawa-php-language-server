<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Protocol\Common\Position;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionContext;
use Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument\CompletionList;

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
