<?php

namespace Realodix\Haiku\Test\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\Redundant\CosmeticCheck;
use Realodix\Haiku\Linter\Rules\Redundant\NetworkCheck;
use Realodix\Haiku\Test\TestCase;

class ScopeConditionalTest extends TestCase
{
    private const RULE = [
        CosmeticCheck::class,
        NetworkCheck::class,
    ];

    #[PHPUnit\Test]
    public function test_1(): void
    {
        $lines = [
            '||example.com^$third-party',
            'example.com##.ads',
            'example.com##.banner',
            'x.com,y.com##.banner2',

            '!#if env_firefox',
            '||example.com^',
            '##.ads',
            '##.banner',
            'x.com##.banner2',
            '!#endif',

            '##[class*="ads" i]',
            '##.banner',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: example.com##.ads is redundant due to more general selector on line 11.'],
            [3, 'Redundant filter: example.com##.banner already covered by ##.banner on line 12.'],
        ]);

        $lines = [
            '##.banner',

            '!#if env_firefox',
            '##.ads',
            '##[class*="banner" i]',
            '!#endif',

            '!#if !env_firefox',
            '##[class*="ads" i]',
            '!#endif',
        ];
        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function test_2(): void
    {
        $lines = [
            '||example.com^$third-party', // 1
            'example.com##.ads', // 2
            'example.com##.banner', // 3

            '!#if env_firefox', // 4
            '||example.com^', // 5
            '##.ads', // 6
            '##.banner', // 7
            '!#invlidendif', // 8

            '!#if env_firefox', // 9
            '||example.com^', // 10
            '##.ads', // 11
            '', // 12

            '##.ads', // 13
            '##.banner', // 14
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: ||example.com^$third-party already covered by ||example.com^ on line 5.'],
            [2, 'Redundant filter: example.com##.ads already covered by ##.ads on line 6.'],
            [3, 'Redundant filter: example.com##.banner already covered by ##.banner on line 7.'],
            [10, 'Redundant filter: ||example.com^ already defined on line 5.'],
            [11, 'Redundant filter: ##.ads already defined on line 6.'],
            [13, 'Redundant filter: ##.ads already defined on line 6.'],
            [14, 'Redundant filter: ##.banner already defined on line 7.'],
        ], self::RULE);
    }
}
