<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Common;

/**
 * Position in a text document.
 *
 * Expressed as zero-based line and zero-based character offset. A position is
 * between two characters like an ‘insert’ cursor in a editor.
 */
class Position
{
    /**
     * Line position in a document (zero-based).
     *
     * @var int
     */
    public $line;

    /**
     * Character offset on a line in a document (zero-based). Assuming that the line is
     * represented as a string, the `character` value represents the gap between the
     * `character` and `character + 1`.
     *
     * If the character value is greater than the line length it defaults back to the
     * line length.
     *
     * @var int
     */
    public $character;

    public function __construct(int $line, int $character)
    {
        $this->line = $line;
        $this->character = $character;
    }
}
