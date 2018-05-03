<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

class GlobalSymbol extends Symbol
{
    const CLASS_ = 'class';
    const FUNCTION_ = 'function';
    const CONST_ = 'const';
    const NAMESPACE_ = 'namespace';

    /**
     * @var string
     */
    public $originalName;

    /**
     * @var bool
     */
    public $isImport = false;
}
