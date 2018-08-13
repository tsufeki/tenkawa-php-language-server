<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Common;

/**
 * A range in a text document.
 *
 * Expressed as (zero-based) start and end positions. A range is comparable to
 * a selection in an editor. Therefore the end position is exclusive. If you
 * want to specify a range that contains a line including the line ending
 * character(s) then use an end position denoting the start of the next line.
 */
class Range
{
    /**
     * The range's start position.
     *
     * @var Position
     */
    public $start;

    /**
     * The range's end position.
     *
     * @var Position
     */
    public $end;

    public function __construct(?Position $start = null, ?Position $end = null)
    {
        if ($start !== null && $end !== null) {
            $this->start = $start;
            $this->end = $end;
        }
    }
}
