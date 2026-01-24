<?php

namespace Realodix\Haiku\Test\Unit;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Helper;
use Realodix\Haiku\Test\TestCase;

class HelperTest extends TestCase
{
    use GeneralProvider;

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

    #[PHPUnit\TestWith(['.example.com/', 'example.com'])]
    #[PHPUnit\TestWith(['/example.com/', 'example.com'])]
    #[PHPUnit\Test]
    public function cleanDomain($input, $expected)
    {
        $this->assertSame($expected, Helper::cleanDomain($input));
    }
}
