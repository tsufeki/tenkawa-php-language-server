<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

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

    public static function limitLength(string $str, int $maxLength = 25): string
    {
        if (strlen($str) > $maxLength) {
            return substr($str, 0, $maxLength - 3) . '...';
        }

        return $str;
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
