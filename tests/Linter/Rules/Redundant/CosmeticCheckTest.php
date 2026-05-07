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

            'example.com,example.org,x.com##.banner',
            'x.com,y.com##.banner',
            'example.com##.banner',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: example.com##.ads already covered by ##.ads on line 1.'],
            [4, 'Redundant filter: ...,example.site##.ads1 already covered by ##.ads1 on line 3.'],
            [6, 'Redundant filter: domain x.com already covered on line 5.'],
            [7, 'Redundant filter: example.com##.banner already covered by ##.banner on line 5.'],
        ]);

        $lines = [
            'example.com##.ads',
            'example.org##.ads',
            'example.com#@#.ads',
            'example.org#@#.ads',
        ];
        $this->analyse($lines, [], self::RULE);
    }

    #[PHPUnit\Test]
    public function redundancy_with_negated_domains(): void
    {
        $lines = [
            '~example.org##.banner1',
            'example.com##.banner1',

            '~x.com,~y.com##[class^="banner2"]',
            '~y.com##[class^="banner2"]',

            '~example.org,example.com##[class^="banner3b"]',
            '~example.org,example.com##[class^="banner3"]',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: example.com##.banner1 already covered by ##.banner1 on line 1.'],
            [4, 'Redundant filter: ~y.com##[class^="banner2"] already covered by ##[class^="banner2"] on line 3.'],
            [5, 'Redundant filter: ~example.org,example.com##[class^="banner3b"] is redundant due to more general selector on line 6.'],
        ]);

        $lines = [
            '##[class^="adv"]',
            '##[class^="advertisement"]',
            '~x.com,~y.com##[class^="ad"]',
            '##[class^="ads"]',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: ##[class^="advertisement"] is redundant due to more general selector on line 1.'],
        ]);

        $lines = [
            // Case 1: Almost-global filter should NOT cover global filter
            '~y.com##[class*="ad" i]',
            '##[class^="ads2"]',
            '##img[alt="advertising"]',
            '~example.org,example.com##img[alt^="adv"]',
            '~example.org,example.com##.banner',
            'example.com##.banner',

            // Case 2: Rules with different exclusions should NOT cover each other
            '~x.com##[class^="banner1"]',
            '~y.com##[class^="banner1"]',

            // Case 3: Mixed rules with different domains should NOT cover each other
            '~example.org,example.com##[class^="2"]',
            '~example.org,example.net##[class^="banner2a"]',
        ];
        $this->analyse($lines);
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

    #[PHPUnit\Test]
    public function chain_selectors(): void
    {
        $lines = [
            '##.ads',
            '##.ads .banner',
            '##.ads .banner',
            '##.ads img',
            'example.org,example.site##.ads img',
            'example.org,example.com,example.site##.ads div.img',
            'example.com##.ads div.img',
        ];

        $this->analyse($lines, [
            [3, 'Redundant filter: ##.ads .banner already defined on line 2.'],
            [5, 'Redundant filter: example.org,example.site##.ads img already covered by ##.ads img on line 4.'],
            [7, 'Redundant filter: example.com##.ads div.img already covered by ##.ads div.img on line 6.'],
        ]);
    }
}
