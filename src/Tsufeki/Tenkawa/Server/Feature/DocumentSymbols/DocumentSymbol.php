<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\DocumentSymbols;

use Tsufeki\Tenkawa\Server\Feature\Common\Range;
use Tsufeki\Tenkawa\Server\Feature\Common\SymbolKind;

/**
 * Represents programming constructs like variables, classes, interfaces etc.
 * that appear in a document. Document symbols can be hierarchical and they
 * have two ranges: one that encloses its definition and one that points to its
 * most interesting range, e.g. the range of an identifier.
 */
class DocumentSymbol
{
    /**
     * The name of this symbol.
     *
     * @var string
     */
    public $name;

    /**
     * More detail for this symbol, e.g the signature of a function. If not
     * provided the name is used.
     *
     * @var string|null
     */
    public $detail;

    /**
     * The kind of this symbol.
     *
     * @see SymbolKind
     *
     * @var int
     */
    public $kind;

    /**
     * Indicates if this symbol is deprecated.
     *
     * @var bool|null
     */
    public $deprecated;

    /**
     * The range enclosing this symbol not including leading/trailing
     * whitespace but everything else like comments. This information is
     * typically used to determine if the clients cursor is inside the symbol
     * to reveal in the symbol in the UI.
     *
     * @var Range
     */
    public $range;

    /**
     * The range that should be selected and revealed when this symbol is being
     * picked, e.g the name of a function. Must be contained by the `range`.
     *
     * @var Range
     */
    public $selectionRange;

    /**
     * Children of this symbol, e.g. properties of a class.
     *
     * @var DocumentSymbol[]|null
     */
    public $children;
}
