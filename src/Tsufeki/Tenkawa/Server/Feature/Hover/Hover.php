<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Hover;

use Tsufeki\Tenkawa\Server\Feature\Common\MarkupContent;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;

/**
 * The result of a hover request.
 */
class Hover
{
    /**
     * The hover's content.
     *
     * @var MarkupContent|string|MarkedString|(string|MarkedString)[]
     */
    public $contents;

    /**
     * An optional range is a range inside a text document that is used to
     * visualize a hover, e.g. by changing the background color.
     *
     * @var Range|null
     */
    public $range;
}
