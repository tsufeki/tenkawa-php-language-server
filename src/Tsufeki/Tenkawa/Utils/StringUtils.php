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

    public static function getShortName(string $fullName): string
    {
        $parts = explode('\\', $fullName);

        return $parts[count($parts) - 1];
    }

    public static function getNamespace(string $fullName): string
    {
        $parts = explode('\\', $fullName);
        array_pop($parts);

        return implode('\\', $parts);
    }
}
