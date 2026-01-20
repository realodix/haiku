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

    #[PHPUnit\Test]
    public function normalizeDomain_pipeSeparated()
    {
        $this->assertSame(
            '~/example\.([a-z]{1,2}|[A-Z]{4,16})/|127.1.0.1|example.*',
            Helper::normalizeDomain('Example.*|~/example\.([a-z]{1,2}|[A-Z]{4,16})/|127.1.0.1', '|'),
        );

        $this->assertSame(
            '~/example\.([a-z]{1,2}|[A-Z]{4,16})/|example.*',
            Helper::normalizeDomain('Example.*|~/example\.([a-z]{1,2}|[A-Z]{4,16})/', '|'),
        );

        $this->assertSame(
            '~/example\.([a-z]{1,2}|[A-Z]{4,16})/|127.1.0.1',
            Helper::normalizeDomain('~/example\.([a-z]{1,2}|[A-Z]{4,16})/|127.1.0.1', '|'),
        );
    }

    #[PHPUnit\Test]
    public function normalizeDomain_commaSeparated()
    {
        $this->assertSame(
            '~/example\.([a-z]{1,2}|[A-Z]{4,16})/,127.1.0.1,example.*',
            Helper::normalizeDomain('Example.*,~/example\.([a-z]{1,2}|[A-Z]{4,16})/,127.1.0.1', ','),
        );

        $this->assertSame(
            '~/example\.([a-z]{1,2}|[A-Z]{4,16})/,example.*',
            Helper::normalizeDomain('Example.*,~/example\.([a-z]{1,2}|[A-Z]{4,16})/', ','),
        );

        $this->assertSame(
            '~/example\.([a-z]{1,2}|[A-Z]{4,16})/,127.1.0.1',
            Helper::normalizeDomain('~/example\.([a-z]{1,2}|[A-Z]{4,16})/,127.1.0.1', ','),
        );
    }
}
