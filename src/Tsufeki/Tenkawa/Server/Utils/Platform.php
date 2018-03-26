<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

class Platform
{
    public static function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    public static function isLinux(): bool
    {
        return PHP_OS === 'Linux';
    }
}
