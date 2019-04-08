<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Symbol;

use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Range;

class Symbol
{
    /**
     * @var string
     */
    public $kind;

    /**
     * @var string[]
     */
    public $referencedNames;

    /**
     * @var Document
     */
    public $document;

    /**
     * @var Range
     */
    public $range;

    /**
     * @var NameContext
     */
    public $nameContext;
}
