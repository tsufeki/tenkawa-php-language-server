<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\PhpStan;

class DummyReflectionType extends \ReflectionType
{
    /**
     * @var string
     */
    private $string;

    /**
     * @var bool
     */
    private $allowsNull;

    /**
     * @var bool
     */
    private $isBuiltin;

    public function __construct(string $string, bool $allowsNull = false, bool $isBuiltin = false)
    {
        if (($string[0] ?? '') === '?') {
            $string = substr($string, 1);
            $allowsNull = true;
        }

        $this->string = ltrim($string, '\\');
        $this->allowsNull = $allowsNull;
        $this->isBuiltin = $isBuiltin;
    }

    public function allowsNull()
    {
        return $this->allowsNull;
    }

    public function isBuiltin()
    {
        return $this->isBuiltin;
    }

    public function __toString()
    {
        return $this->string;
    }
}
