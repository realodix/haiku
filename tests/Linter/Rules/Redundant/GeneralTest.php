<?php

namespace Realodix\Haiku\Test\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class GeneralTest extends TestCase
{
    // private const RULE = [
    //     \Realodix\Haiku\Linter\Rules\Redundant\CosmeticCheck::class,
    //     \Realodix\Haiku\Linter\Rules\Redundant\NetworkCheck::class,
    // ];

    #[PHPUnit\Test]
    public function redundant_1(): void
    {
        $lines = [
            '/ads$domain=~example.org', // L1: Almost Global (covers all except example.org)
            '/ads',                     // L2: Global (covers all)
            '/ads$domain=example.com',  // L3: Local (covers only example.com)

            '~example.com##.ads',   // L1: Almost Global (covers all except example.org)
            '##.ads',               // L2: Global (covers all)
            'example.org##.ads',    // L3: Local (covers only example.com)
        ];

        $this->analyse($lines, [
            [1, 'Redundant filter: /ads$domain=~example.org already covered by /ads on line 2.'],
            [3, 'Redundant filter: /ads$domain=example.com already covered by /ads on line 2.'],
            [4, 'Redundant filter: ~example.com##.ads already covered by ##.ads on line 5.'],
            [6, 'Redundant filter: example.org##.ads already covered by ##.ads on line 5.'],
        ]);
    }

    #[PHPUnit\Test]
    public function redundant_2(): void
    {
        $lines = [
            '/banner',                     // L1: Truly Global
            '/banner$domain=~example.org', // L2: Almost Global
            '/banner$domain=example.com',  // L3: Local
        ];

        // L3 should be considered redundant by L1, not L2.
        // L2 is also redundant by L1.
        $this->analyse($lines, [
            [2, 'Redundant filter: /banner$domain=~example.org already covered by /banner on line 1.'],
            [3, 'Redundant filter: /banner$domain=example.com already covered by /banner on line 1.'],
        ]);

        $lines = [
            '##.ads',             // L1: Truly Global
            '~example.org##.ads', // L2: Almost Global
            'example.com##.ads',  // L3: Local
        ];

        // L3 should be considered redundant by L1, not L2.
        // L2 is also redundant by L1.
        $this->analyse($lines, [
            [2, 'Redundant filter: ~example.org##.ads already covered by ##.ads on line 1.'],
            [3, 'Redundant filter: example.com##.ads already covered by ##.ads on line 1.'],
        ]);
    }

    #[PHPUnit\Test]
    public function redundant_3(): void
    {
        $lines = [
            '##[class~="ads"]',
            '##div[class="ads banner"]',
        ];

        // ##div[class="ads banner"] is redundant because it's more specific than ##[class~="ads"]
        $this->analyse($lines, [
            [2, 'Redundant filter: ##div[class="ads banner"] is redundant due to more general selector on line 1.'],
        ]);
    }

    #[PHPUnit\Test]
    public function isBetter_prefers_more_general_pattern(): void
    {
        $lines = [
            '/ads-banner-',     // more specific
            '/ads-',            // more general (should win)
            '/ads-banner-top',  // target
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: /ads-banner- already covered by /ads- on line 2.'],
            [3, 'Redundant filter: /ads-banner-top already covered by /ads- on line 2.'],
        ]);

        $lines = [
            '/ads',                          // L1: Truly Global
            '/ads$image',                    // L2: With options
            '/ads$image,domain=example.com', // L3: Target
        ];
        // L3 should be considered redundant by L1 (most common), not L2.
        // L2 is also redundant by L1.
        $this->analyse($lines, [
            [2, 'Redundant filter: /ads$image already covered by /ads on line 1.'],
            [3, 'Redundant filter: /ads$image,domain=example.com already covered by /ads on line 1.'],
        ]);
    }
}
