<?php

namespace Realodix\Haiku\Test\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\Redundant\CosmeticCheck;
use Realodix\Haiku\Test\TestCase;

class CosmeticCheckTest extends TestCase
{
    private const RULE = [CosmeticCheck::class];

    #[PHPUnit\Test]
    public function case_sensitive(): void
    {
        $lines = [
            '##.ads',
            '##.ADS', // Not a duplicate (case-sensitive as per user request)
            'example.com##.ads',
            'example.com##.ads', // Duplicate
            '##[id^="div-gpt-ad"]', // Duplicate
            '##[id^="div-gpt-ad"]', // Duplicate
        ];

        $this->analyse($lines, [
            [3, 'Redundant filter: example.com##.ads already covered by ##.ads on line 1.'],
            [4, 'Redundant filter: example.com##.ads already defined on line 3.'],
            [6, 'Redundant filter: ##[id^="div-gpt-ad"] already defined on line 5.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function redundant(): void
    {
        $lines = [
            '##.ads',
            'example.com##.ads', // Redundant
            '##.ads1',
            // Redundant with more than 2 domains
            'example.come,example.org,example.site##.ads1',
            // '!',
            // '##.adv',
            // '##div.adv', // Redundant by ##.adv
            // '##.menu > .adv', // Redundant by ##.adv
            // '##.menu .adv', // Redundant by ##.adv
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: example.com##.ads already covered by ##.ads on line 1.'],
            [4, 'Redundant filter: ...,example.site##.ads1 already covered by ##.ads1 on line 3.'],
        ], self::RULE);

        $lines = [
            'example.com##.ads',
            'example.org##.ads',
            'example.com#@#.ads',
            'example.org#@#.ads',
        ];
        $this->analyse($lines, [], self::RULE);
    }

    #[PHPUnit\Test]
    public function ignore_comments_and_directives(): void
    {
        $lines = [
            '##.ads',
            '! Comment',
            '! Comment',
            '!#if ext_abp',
            '##.ads',
            '!#if ext_abp',
            '##.ads',
        ];

        $this->analyse($lines, [
            [5, 'Redundant filter: ##.ads already defined on line 1.'],
            [7, 'Redundant filter: ##.ads already defined on line 1.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function ignore_preparsing_directives(): void
    {
        $lines = [
            '##.ads',
            '[$domain=example.com]##.ads',
            '[$domain=example.com]##.ads',
            '##.ads',
        ];

        $this->analyse($lines, [
            [4, 'Redundant filter: ##.ads already defined on line 1.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function respectCosmeticException(): void
    {
        $lines = [
            '##.ads',
            'example.com,example.org,example.site##.ads',
            '@@||example.org^$ghide',
            'x.com##.ads',
            '@@||x.com^$ghide',
            'y.com##.ads',
            'z.com##.ads',
            '@@*$generichide,domain=y.com|z.com',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: domain example.com already covered on line 1.'],
            [2, 'Redundant filter: domain example.site already covered on line 1.'],
        ]);

        $lines = [
            '##.ads',
            'example.com,example.org,example.site##.ads',
            '@@||example.org^$ehide',
            'x.com##.ads',
            '@@||x.com^$ghide',
            'y.com##.ads',
            'z.com##.ads',
            '@@*$elemhide,domain=y.com|z.com',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: domain example.com already covered on line 1.'],
            [2, 'Redundant filter: domain example.site already covered on line 1.'],
        ]);
    }
}
