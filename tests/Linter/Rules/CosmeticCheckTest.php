<?php

namespace Realodix\Haiku\Test\Linter\Rules;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Test\TestCase;

class CosmeticCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(LinterConfig::class)->rules = [
            'check_id_selector_start' => true,
        ];
    }

    #[PHPUnit\Test]
    public function id_selector_invalid(): void
    {
        $lines = [
            '###1800number_bo',
            'example.com###1800number_bo',
            'example.com###1800number_bo #13_3623',
            'example.com##h3[style*="color:#999"] #1800number_bo + path[fill="#9E9E9E"]',
        ];

        $this->analyse($lines, [
            [1, 'Invalid filter: ID selector #1800number_bo cannot start with a number.'],
            [2, 'Invalid filter: ID selector #1800number_bo cannot start with a number.'],
            [3, 'Invalid filter: ID selector #1800number_bo cannot start with a number.'],
            [3, 'Invalid filter: ID selector #13_3623 cannot start with a number.'],
            [4, 'Invalid filter: ID selector #1800number_bo cannot start with a number.'],
        ]);

        $lines = [
            'example.com###module-293\#3-0-0',
            'example.com##div[style="background-color:#f4f4f4;color:#333;"]',
            'example.com##.shareWidget:style(background: #0000 !important)',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function abp_ext_valid(): void
    {
        $lines = [
            'example.com#?#:-abp-has(.sponsored)',
            'example.com#?#:-abp-contains(filters)',
            'example.com#?#:-abp-properties(background-color: #3D9C4F;)',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function abp_ext_invalid(): void
    {
        $lines = [
            'example.com##:-abp-has(.sponsored)',
            'example.com##:-abp-contains(filters)',
            'example.com##:-abp-properties(background-color: #3D9C4F;)',
        ];

        $this->analyse($lines, [
            [1, 'Invalid filter: -abp-has requires #?# separator syntax.'],
            [2, 'Invalid filter: -abp-contains requires #?# separator syntax.'],
            [3, 'Invalid filter: -abp-properties requires #?# separator syntax.'],
        ]);
    }
}
