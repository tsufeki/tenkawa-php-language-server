<?php declare(strict_types=1);

namespace Foo;

class SelfCompletion
{
    /**
     * @var int
     */
    public $pubField = 7;

    /**
     * @return self
     */
    public function method()
    {
    }

    public static function staticMethod(): self
    {
    }
}
