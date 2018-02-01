<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Protocol\Server\LifeCycle;

class CompletionOptions
{
    /**
     * The server provides support to resolve additional information for
     * a completion item.
     *
     * @var bool
     */
    public $resolveProvider = false;

    /**
     * The characters that trigger completion automatically.
     *
     * @var string[]|null
     */
    public $triggerCharacters;
}
