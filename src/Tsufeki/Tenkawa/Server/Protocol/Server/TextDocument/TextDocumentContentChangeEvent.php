<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Server\TextDocument;

use Tsufeki\Tenkawa\Server\Protocol\Common\Range;

/**
 * An event describing a change to a text document.
 *
 * If range and rangeLength are omitted the new text is considered to be the
 * full content of the document.
 */
class TextDocumentContentChangeEvent
{
    /**
     * The range of the document that changed.
     *
     * @var Range|null
     */
    public $range;

    /**
     * The length of the range that got replaced.
     *
     * @var int|null
     */
    public $rangeLength;

    /**
     * The new text of the range/document.
     *
     * @var string
     */
    public $text;
}
