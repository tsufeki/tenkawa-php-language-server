<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Server\LifeCycle;

class InitializeResult
{
    /**
     * The capabilities the language server provides.
     *
     * @var ServerCapabilities
     */
    public $capabilities;
}
