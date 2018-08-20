<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

class FuzzyMatcher
{
    /**
     * @return int Match score, \PHP_INT_MIN when not matched.
     */
    public function match(string $pattern, string $str): int
    {
        $patternLower = strtolower($pattern);
        $strLower = strtolower($str);

        $p = $s = 0;
        while (isset($patternLower[$p]) && isset($strLower[$s])) {
            if ($patternLower[$p] === $strLower[$s]) {
                $p++;
            }
            $s++;
        }

        return isset($patternLower[$p]) ? \PHP_INT_MIN : 1;
    }
}
