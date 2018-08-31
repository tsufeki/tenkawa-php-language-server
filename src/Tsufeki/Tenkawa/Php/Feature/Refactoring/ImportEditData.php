<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\Refactoring;

class ImportEditData
{
    /**
     * @var int
     */
    public $lineNo;

    /**
     * @var string
     */
    public $indent = '';

    /**
     * @var bool
     */
    public $appendEmptyLine = false;
}
