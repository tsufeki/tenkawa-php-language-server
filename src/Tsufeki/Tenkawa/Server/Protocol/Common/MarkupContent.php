<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Common;

class MarkupContent
{
    /**
     * The type of the Markup.
     *
     * @see MarkupKind
     *
     * @var string
     */
    public $kind;

    /**
     * The content itself.
     *
     * @var string
     */
    public $string;
}
