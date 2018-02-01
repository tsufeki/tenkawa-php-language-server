<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Reflection;

class NameContext
{
    // Fully qualified names with leading \

    /**
     * @var string
     */
    public $namespace = '\\';

    /**
     * @var array<string,string> alias => fully qualified name
     */
    public $uses = [];

    /**
     * @var array<string,string> alias => fully qualified name
     */
    public $functionUses = [];

    /**
     * @var array<string,string> alias => fully qualified name
     */
    public $constUses = [];

    /**
     * @var string|null
     */
    public $class = null;

    public function resolveClass(string $name): string
    {
        if (($name[0] ?? '') === '\\') {
            return $name;
        }

        $parts = explode('\\', $name);
        $first = $parts[0];
        if (isset($this->uses[$first])) {
            $parts[0] = $this->uses[$first];

            return implode('\\', $parts);
        }

        return $this->namespace . '\\' . $name;
    }
}
