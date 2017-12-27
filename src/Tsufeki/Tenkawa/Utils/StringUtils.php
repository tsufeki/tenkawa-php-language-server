<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Utils;

class StringUtils
{
    public static function startsWith(string $haystack, string $needle): bool
    {
        return $needle === '' || ($haystack !== '' && substr_compare($haystack, $needle, 0, strlen($needle)) === 0);
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        return $needle === '' || ($haystack !== '' && substr_compare($haystack, $needle, -strlen($needle)) === 0);
    }
}
