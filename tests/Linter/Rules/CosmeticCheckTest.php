<?php

namespace Realodix\Haiku\Test\Linter\Rules;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\CosmeticCheck;
use Realodix\Haiku\Test\TestCase;

class CosmeticCheckTest extends TestCase
{
    private const RULE = [
        CosmeticCheck::class,
    ];

    #[PHPUnit\Test]
    public function badSeparator(): void
    {
        // https://github.com/ABPindo/indonesianadblockrules/blob/48909402ea/src/advert/specific_hide.txt#L325
        $lines = [
            'shinigami09.com###.ad-container',
            'shinigami09.com####ad-container',
        ];

        $this->analyse($lines, [
            [1, 'Bad separator.'],
            [2, 'Bad separator.'],
        ]);
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
        ], self::RULE);

        $lines = [
            'example.com###module-293\#3-0-0',
            'example.com##div[style="background-color:#f4f4f4;color:#333;"]',
            'example.com##.shareWidget:style(background: #0000 !important)',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function checkColonEscape(): void
    {
        $lines = [
            'example.com##.min-\[1100px\]:col-span-8:foo-bar',
            // https://github.com/ABPindo/indonesianadblockrules/blob/90e59175b3/src/advert/specific_hide.txt#L742
            'example.com##.dark\\\:bg-gray-700',
            // https://github.com/ABPindo/indonesianadblockrules/blob/90e59175b3/src/adult/adult_specific_hide.txt#L200
            'example.com##.dark:bg-gray-700',
        ];
        $this->analyse($lines, [
            [1, 'Invalid filter: Colon ":col-span-8" must be escaped with a backslash.'],
            [1, 'Invalid filter: Colon ":foo-bar" must be escaped with a backslash.'],
            [2, 'Invalid filter: Colon "\\\:bg-gray-700" has too many backslashes.'],
            [3, 'Invalid filter: Colon ":bg-gray-700" must be escaped with a backslash.'],
        ]);

        $lines = [
            'example.com##.dark\:bg-gray-700',
            'hk01.com##div[class^="lg\:min-h-[250px] hidden lg:flex lg:items-center"]',
            'xbitlabs.com##.flex.items-center.justify-center:has(> span:first-child + div[data-fuse]:last-child)',
            'fastpic.org,~new.fastpic.org##a#imglink[href*="/fullview/"] {display:inline-block;overflow:hidden;}',
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
