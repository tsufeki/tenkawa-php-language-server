<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

class GlobalSymbol extends Symbol
{
    const CLASS_ = 'class';
    const FUNCTION_ = 'function';
    const CONST_ = 'const';
    const NAMESPACE_ = 'namespace';

    const KINDS = [
        self::CLASS_,
        self::FUNCTION_,
        self::CONST_,
    ];

    /**
     * @var string
     */
    public $originalName;

    /**
     * @var bool
     */
    public $isImport = false;

    /**
     * @var bool
     */
    public $isNewExpression = false;
}
