<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Reflection;

class NameContext
{
    // Fully qualified names with leading \

    /**
     * @var string
     */
    public $namespace = '\\';

    /**
     * @var array<string|string> alias => fully qualified name
     */
    public $uses = [];

    /**
     * @var array<string|string> alias => fully qualified name
     */
    public $functionUses = [];

    /**
     * @var array<string|string> alias => fully qualified name
     */
    public $constUses = [];

    /**
     * @var string|null
     */
    public $class = null;
}
