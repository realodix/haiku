<?php

namespace Realodix\Haiku\Test\Unit;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Helper;
use Realodix\Haiku\Test\TestCase;

class HelperTest extends TestCase
{
    use GeneralProvider;

    #[PHPUnit\Test]
    public function uniqueSortBy()
    {
        // '0' should not be removed by `array_filter()`
        $input = ['0', '0'];
        $expected = ['0'];
        $output = Helper::uniqueSortBy($input, fn($s) => $s);

        $this->assertSame($expected, $output);
    }

    #[PHPUnit\DataProvider('isCosmeticRuleProvider')]
    #[PHPUnit\Test]
    public function isCosmeticRule($data)
    {
        $this->assertTrue(Helper::isCosmeticRule($data));
    }

    #[PHPUnit\DataProvider('isNotCosmeticRuleProvider')]
    #[PHPUnit\Test]
    public function isNotCosmeticRule($data)
    {
        $this->assertFalse(Helper::isCosmeticRule($data));
    }

    #[PHPUnit\DataProvider('cssEscapeProvider')]
    #[PHPUnit\Test]
    public function cssEscape($input, $expected)
    {
        $this->assertSame($expected, Helper::cssEscape($input));
    }

    /**
     * https://github.com/mathiasbynens/CSS.escape/blob/4b25c283e/tests/tests.js
     */
    public static function cssEscapeProvider(): array
    {
        return [
            // allowed_characters
            ['abc', 'abc'],
            ['A_Z-09', 'A_Z-09'],

            // null character
            ["\0", "\u{FFFD}"],
            ["a\0", "a\u{FFFD}"],
            ["\0b", "\u{FFFD}b"],
            ["a\0b", "a\u{FFFD}b"],
            // replacement character passthrough
            ["\u{FFFD}", "\u{FFFD}"],
            ["a\u{FFFD}", "a\u{FFFD}"],
            ["\u{FFFD}b", "\u{FFFD}b"],
            ["a\u{FFFD}b", "a\u{FFFD}b"],
            // control characters
            ["\x01", '\1 '],
            ["\x1F", '\1f '],
            ["\x7F", '\7f '],
            [chr(0x01).chr(0x02).chr(0x1E).chr(0x1F), '\1 \2 \1e \1f '],
            // first character digit
            ['1abc', '\31 abc'],
            ['9test', '\39 test'],
            // second character digit after dash
            ['-1abc', '-\31 abc'],
            ['-9foo', '-\39 foo'],
            // single dash
            ['-', '\-'],
            ['-a', '-a'],
            ['--', '--'],
            ['--a', '--a'],
            // unicode passthrough
            ['é', 'é'],
            ["\x80\x2D\x5F\xA9", "\x80\x2D\x5F\xA9"],
            ["\xA0\xA1\xA2", "\xA0\xA1\xA2"],
            // other characters are escaped
            ['#', '\#'],
            ['.', '\.'],
            ['[', '\['],
            [':', '\:'],
            // simple_escape_characters
            [' !xy', '\ \!xy'],
            // astral_symbol_passthrough
            ["\u{1D306}", "\u{1D306}"],
        ];
    }
}
