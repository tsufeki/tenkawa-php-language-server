<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils\PriorityKernel;

use Recoil\Recoil;

class Priority
{
    const INTERACTIVE = -1000;
    const FOREGROUND = -2000;
    const BACKGROUND = -3000;

    public static function set(int $priority): \Generator
    {
        $strand = yield Recoil::strand();
        if ($strand instanceof PriorityStrand) {
            $strand->setPriority($priority);
        }

        yield;
    }

    public static function interactive(int $bonus = 0): \Generator
    {
        return self::set(self::INTERACTIVE + $bonus);
    }

    public static function foreground(int $bonus = 0): \Generator
    {
        return self::set(self::FOREGROUND + $bonus);
    }

    public static function background(int $bonus = 0): \Generator
    {
        return self::set(self::BACKGROUND + $bonus);
    }
}
