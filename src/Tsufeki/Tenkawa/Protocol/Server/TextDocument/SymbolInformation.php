<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Server\TextDocument;

use Tsufeki\Tenkawa\Protocol\Common\Location;

/**
 * Represents information about programming constructs like variables, classes,
 * interfaces etc.
 */
class SymbolInformation
{
    /**
     * The name of this symbol.
     *
     * @var string
     */
    public $name;

    /**
     * The kind of this symbol.
     *
     * @see SymbolKind
     *
     * @var int
     */
    public $kind;

    /**
     * The location of this symbol.
     *
     * The location's range is used by a tool to reveal the location in the
     * editor. If the symbol is selected in the tool the range's start
     * information is used to position the cursor. So the range usually spawns
     * more then the actual symbol's name and does normally include thinks like
     * visibility modifiers.
     *
     * The range doesn't have to denote a node range in the sense of a abstract
     * syntax tree. It can therefore not be used to re-construct a hierarchy of
     * the symbols.
     *
     * @var Location
     */
    public $location;

    /**
     * The name of the symbol containing this symbol.
     *
     * This information is for user interface purposes (e.g. to render a
     * qualifier in the user interface if necessary). It can't be used to
     * re-infer a hierarchy for the document symbols.
     *
     * @var string|null
     */
    public $containerName;
}
