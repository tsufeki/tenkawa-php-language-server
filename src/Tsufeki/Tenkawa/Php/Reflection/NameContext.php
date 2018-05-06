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
        return $this->resolve($name, $this->uses, false)[0];
    }

    /**
     * @return string[]
     */
    public function resolveFunction(string $name): array
    {
        return $this->resolve($name, $this->functionUses, true);
    }

    /**
     * @return string[]
     */
    public function resolveConst(string $name): array
    {
        return $this->resolve($name, $this->constUses, true);
    }

    /**
     * @param array<string,string> $uses alias => fully qualified name
     *
     * @return string[]
     */
    private function resolve(string $name, array $uses, bool $addGlobal): array
    {
        if (($name[0] ?? '') === '\\') {
            return [$name];
        }

        $parts = explode('\\', $name);
        if (count($parts) !== 1) {
            $uses = $this->uses;
            $addGlobal = false;
        }

        $result = [];
        $resolved = $this->resolveUses($parts, $uses);

        if ($resolved !== null) {
            $result[] = $resolved;
        } else {
            $result[] = $this->namespace . '\\' . $name;
            if ($addGlobal) {
                $result[] = '\\' . $name;
            }
        }

        return $result;
    }

    /**
     * @param string[]             $parts
     * @param array<string,string> $uses  alias => fully qualified name
     *
     * @return string|null
     */
    private function resolveUses(array $parts, array $uses)
    {
        $first = $parts[0];
        if (isset($uses[$first])) {
            $parts[0] = $uses[$first];

            return implode('\\', $parts);
        }

        return null;
    }
}
