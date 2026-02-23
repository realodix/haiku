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
}
