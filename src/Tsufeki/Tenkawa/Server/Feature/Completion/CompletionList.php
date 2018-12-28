<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Completion;

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

    /**
     * @param self[] $completionsLists
     */
    public static function merge(array $completionsLists): self
    {
        $completions = new self();
        if ($completionsLists === []) {
            return $completions;
        }

        $completions->items = array_merge(...array_map(function (CompletionList $list) {
            return $list->items;
        }, $completionsLists));

        $completions->isIncomplete = 0 !== array_sum(array_map(function (CompletionList $list) {
            return $list->isIncomplete;
        }, $completionsLists));

        return $completions;
    }
}
