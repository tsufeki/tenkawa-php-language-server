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
        return $this->resolveUse($name, $this->uses) ?? $this->namespace . '\\' . $name;
    }

    /**
     * @return string[]
     */
    public function resolveFunction(string $name): array
    {
        $resolved = $this->resolveUse($name, $this->functionUses);

        return $resolved !== null
            ? [$resolved]
            : [$this->namespace . '\\' . $name, '\\' . $name];
    }

    /**
     * @return string[]
     */
    public function resolveConst(string $name): array
    {
        $resolved = $this->resolveUse($name, $this->constUses);

        return $resolved !== null
            ? [$resolved]
            : [$this->namespace . '\\' . $name, '\\' . $name];
    }

    /**
     * @param array<string,string> $uses alias => fully qualified name
     *
     * @return string|null
     */
    private function resolveUse(string $name, array $uses)
    {
        if (($name[0] ?? '') === '\\') {
            return $name;
        }

        $parts = explode('\\', $name);
        $first = $parts[0];
        if (isset($uses[$first])) {
            $parts[0] = $uses[$first];

            return implode('\\', $parts);
        }

        return null;
    }
}
