<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Refactor;

class Indent
{
    /**
     * @var int
     */
    public $tabs = 0;

    /**
     * @var int
     */
    public $spaces = 0;

    public function __construct(int $tabs = 0, int $spaces = 0)
    {
        $this->tabs = $tabs;
        $this->spaces = $spaces;
    }

    public function render(): string
    {
        return str_repeat("\t", $this->tabs) . str_repeat(' ', $this->spaces);
    }

    public static function min(self $a, self $b): self
    {
        if ($a->tabs === $b->tabs) {
            return $a->spaces < $b->spaces ? $a : $b;
        }

        return $a->tabs < $b->tabs ? $a : $b;
    }
}
