<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument;

/**
 * Contains additional information about the context in which a completion
 * request is triggered.
 */
class CompletionContext
{
    /**
     * How the completion was triggered.
     *
     * @see CompletionTriggerKind
     *
     * @var int
     */
    public $triggerKind;

    /**
     * The trigger character (a single character) that has trigger code
     * complete.
     *
     * Is undefined if `$triggerKind !== CompletionTriggerKind::TRIGGER_CHARACTER`
     *
     * @var string|null
     */
    public $triggerCharacter;
}
