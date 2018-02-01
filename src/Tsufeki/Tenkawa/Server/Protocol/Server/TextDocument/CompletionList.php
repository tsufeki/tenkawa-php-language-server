<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument;

/**
 * Represents a collection of completion items to be presented in the editor.
 */
class CompletionList
{
    /**
     * This list it not complete.
     *
     * Further typing should result in recomputing this list.
     *
     * @var bool
     */
    public $isIncomplete = false;

    /**
     * The completion items.
     *
     * @var CompletionItem[]
     */
    public $items = [];
}
