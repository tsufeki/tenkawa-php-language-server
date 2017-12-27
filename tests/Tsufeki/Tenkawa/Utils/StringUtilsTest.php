<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Utils;

use PHPUnit\Framework\TestCase;
use Tsufeki\Tenkawa\Utils\StringUtils;

/**
 * @covers \Tsufeki\Tenkawa\Utils\StringUtils
 */
class StringUtilsTest extends TestCase
{
    /**
     * @dataProvider data_starts_with
     */
    public function test_starts_with($haystack, $needle, $result)
    {
        $this->assertSame($result, StringUtils::startsWith($haystack, $needle));
    }

    public function data_starts_with(): array
    {
        return [
            ['', '', true],
            ['abc', '', true],
            ['', 'abc', false],
            ['abc', 'a', true],
            ['a', 'abc', false],
            ['abc', 'ab', true],
            ['ab', 'abc', false],
            ['abc', 'abc', true],
            ['abc', 'abcd', false],
            ['abc', 'Abc', false],
            ['abc', 'abD', false],
            ['abc', 'aB', false],
        ];
    }

    /**
     * @dataProvider data_ends_with
     */
    public function test_ends_with($haystack, $needle, $result)
    {
        $this->assertSame($result, StringUtils::endsWith($haystack, $needle));
    }

    public function data_ends_with(): array
    {
        return [
            ['', '', true],
            ['abc', '', true],
            ['', 'abc', false],
            ['abc', 'c', true],
            ['c', 'abc', false],
            ['abc', 'bc', true],
            ['bc', 'abc', false],
            ['a', 'abc', false],
            ['abc', 'abc', true],
            ['abc', 'zabc', false],
            ['abc', 'Abc', false],
            ['abc', 'abD', false],
            ['abc', 'Bc', false],
        ];
    }
}
