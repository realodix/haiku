<?php

namespace Realodix\Haiku\Test\Unit\Support;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Support\Util;
use Realodix\Haiku\Test\TestCase;

class UtilTest extends TestCase
{
    use UtilProvider;

    #[PHPUnit\DataProvider('isCosmeticRuleProvider')]
    #[PHPUnit\Test]
    public function isCosmeticRule($data)
    {
        $this->assertTrue(Util::isCosmeticRule($data));
    }

    #[PHPUnit\DataProvider('isNotCosmeticRuleProvider')]
    #[PHPUnit\Test]
    public function isNotCosmeticRule($data)
    {
        $this->assertFalse(Util::isCosmeticRule($data));
    }

    #[PHPUnit\DataProvider('isMetaLineProvider')]
    #[PHPUnit\Test]
    public function isMetaLine($data)
    {
        $this->assertTrue(Util::isMetaLine($data));
    }

    #[PHPUnit\DataProvider('isNotMetaLineProvider')]
    #[PHPUnit\Test]
    public function isNotMetaLine($data)
    {
        $this->assertFalse(Util::isMetaLine($data));
    }

    #[PHPUnit\DataProvider('splitOptions_provider')]
    #[PHPUnit\Test]
    public function splitOptions($string, $expected)
    {
        $this->assertSame(
            $expected,
            Util::splitOptions($string),
        );
    }

    #[PHPUnit\DataProvider('cssEscapeProvider')]
    #[PHPUnit\Test]
    public function cssEscape($input, $expected)
    {
        $this->assertSame($expected, Util::cssEscape($input));
    }
}
