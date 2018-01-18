<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Server\TextDocument;

/**
 * How a completion was triggered.
 */
class CompletionTriggerKind
{
    /**
     * Completion was triggered by typing an identifier (24x7 code complete),
     * manual invocation (e.g Ctrl+Space) or via API.
     */
    const INVOKED = 1;

    /**
     * Completion was triggered by a trigger character specified by the
     * `triggerCharacters` properties of the `CompletionRegistrationOptions`.
     */
    const TRIGGER_CHARACTER = 2;
}
