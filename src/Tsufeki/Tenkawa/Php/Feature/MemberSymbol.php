<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use Tsufeki\Tenkawa\Php\TypeInference\Type;

class MemberSymbol extends Symbol
{
    const PROPERTY = 'property';
    const METHOD = 'method';
    const CLASS_CONST = 'class_const';

    /**
     * @var bool
     */
    public $static = false;

    /**
     * @var Type
     */
    public $objectType;

    /**
     * @var bool
     */
    public $literalClassName = false;

    /**
     * @var bool
     */
    public $isInObjectContext = false;
}
