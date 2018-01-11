<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Common;

/**
 * Describes the content type that a client supports in various result literals
 * like `Hover`, `ParameterInfo` or `CompletionItem`.
 *
 * Please note that `MarkupKinds` must not start with a `$`. This kinds are
 * reserved for internal usage.
 */
class MarkupKind
{
    /**
     * Plain text is supported as a content format.
     */
    const PLAIN_TEXT = 'plaintext';

    /**
     * Markdown is supported as a content format.
     */
    const MARKDOWN = 'markdown';
}
