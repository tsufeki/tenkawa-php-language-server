<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Hover;

/**
 * MarkedString can be used to render human readable text.
 *
 * It is either a markdown string or a code-block that provides a language and
 * a code snippet. The language identifier is semantically equal to the
 * optional language identifier in fenced code blocks in GitHub issues. See
 * https://help.github.com/articles/creating-and-highlighting-code-blocks/#syntax-highlighting
 *
 * The pair of a language and a value is an equivalent to markdown:
 * ```${language}
 * ${value}
 * ```
 *
 * Note that markdown strings will be sanitized - that means html will be escaped.
 *
 * @deprecated use MarkupContent instead.
 */
class MarkedString
{
    /**
     * @var string
     */
    public $language;

    /**
     * @var string
     */
    public $value;
}
