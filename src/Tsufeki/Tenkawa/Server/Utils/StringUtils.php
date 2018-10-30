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

    /**
     * @param array<string> $matches
     */
    public static function match(string $regex, string $str, array &$matches = null): bool
    {
        $result = preg_match($regex, $str, $matches);

        if ($result === false) {
            throw new \InvalidArgumentException(__METHOD__ . '(): invalid argument');
        }

        return (bool)$result;
    }

    /**
     * @param string|\Closure $replacement
     */
    public static function replace(string $regex, $replacement, string $str): string
    {
        if ($replacement instanceof \Closure) {
            $result = preg_replace_callback($regex, $replacement, $str);
        } else {
            $result = preg_replace($regex, $replacement, $str);
        }

        if ($result === null) {
            throw new \InvalidArgumentException(__METHOD__ . '(): invalid argument');
        }

        return $result;
    }
}
