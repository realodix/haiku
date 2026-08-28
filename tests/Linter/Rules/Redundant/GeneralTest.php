<?php

namespace Realodix\Haiku\Test\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class GeneralTest extends TestCase
{
    #[PHPUnit\Test]
    public function parent_domain(): void
    {
        $lines = [
            'ads.example.com,example.com###ads1',
            '*$domain=ads.example.com|example.com',
        ];
        $this->analyse($lines, [
            [1, 'Redundant domain: ads.example.com is covered by example.com'],
            [2, 'Redundant domain: ads.example.com is covered by example.com'],
        ]);

        $lines = [
            '~ads.example.com,example.com###ads1',
            '*$domain=~ads.example.com|example.com',
        ];
        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function tld(): void
    {
        $lines = [
            '~example.com,com###ads1',
            '~ads.google.com,com###ads2',
        ];
        $this->analyse($lines);

        $lines = [
            'example.com,com###ads1',
            'ads.example.com,com###ads2',
            'ads.example.com,com,example.com###ads3',
        ];
        $this->analyse($lines, [
            [1, 'Redundant domain: example.com is covered by com TLD'],
            [2, 'Redundant domain: ads.example.com is covered by com TLD'],
            [3, 'Redundant domain: ads.example.com is covered by com TLD'],
            [3, 'Redundant domain: example.com is covered by com TLD'],
        ]);

        $lines = [
            '*$domain=example.com|com',
            '/ads1-$domain=ads.example.com|com',
            '/ads2-$domain=ads.example.com|com|example.com',
        ];
        $this->analyse($lines, [
            [1, 'Redundant domain: example.com is covered by com TLD'],
            [2, 'Redundant domain: ads.example.com is covered by com TLD'],
            [3, 'Redundant domain: ads.example.com is covered by com TLD'],
            [3, 'Redundant domain: example.com is covered by com TLD'],
        ]);
    }

    #[PHPUnit\Test]
    public function tld_wildcard(): void
    {
        $lines = [
            '~ads.example.com,example.*###ads1',
            'ads.example.com,ads.*###ads2',
            '*$domain=~ads.example.com|example.*',
            '/ads1a-$domain=ads.example.com|ads.*',
        ];
        $this->analyse($lines);

        $lines = [
            'example.com,example.*###ads1',
            'ads.example.com,example.*,example.com###ads1a',
        ];
        $this->analyse($lines, [
            [1, 'Redundant domain: example.com is covered by example.*'],
            [2, 'Redundant domain: ads.example.com is covered by example.*'],
            [2, 'Redundant domain: example.com is covered by example.*'],
        ]);

        $lines = [
            '*$domain=example.com|example.*',
            '/ads1a-$domain=ads.example.com|example.*|example.com',
        ];
        $this->analyse($lines, [
            [1, 'Redundant domain: example.com is covered by example.*'],
            [2, 'Redundant domain: ads.example.com is covered by example.*'],
            [2, 'Redundant domain: example.com is covered by example.*'],
        ]);
    }

    #[PHPUnit\Test]
    public function cross_rules_parent_and_tld(): void
    {
        $lines = [
            'example.com###ads1',
            'com###ads1',
            'ads.example.com###ads1',
            '*$domain=ads.example.com',
            '*$domain=com',
            '*$domain=example.com',
        ];
        $this->analyse($lines, [
            [1, 'Duplicate filter: identical to the filter rule on line 2'],
            [3, 'Duplicate filter: identical to the filter rule on line 2'],
            [4, 'Redundant filter: domain ads.example.com already covered on line 5'],
            [6, 'Redundant filter: domain example.com already covered on line 5'],
        ]);

        $lines = [
            'example.com###ads1',
            'com###ads1',
            '*$domain=com',
            '*$domain=example.com',
        ];
        $this->analyse($lines, [
            [1, 'Duplicate filter: identical to the filter rule on line 2'],
            [4, 'Redundant filter: domain example.com already covered on line 3'],
        ]);

        $lines = [
            'example.com,wikipedia.com###ads1a',
            'example.com###ads1a',
            'example.com###ads1b',
            'example.com,wikipedia.com###ads1b',
            '/ads1a-$domain=example.com|wikipedia.com',
            '/ads1a-$domain=example.com',
            '/ads1b-$domain=example.com',
            '/ads1b-$domain=example.com|wikipedia.com',

            'example.*,wikipedia.com###ads2a', // 9
            'example.com###ads2a', // 10
            'example.com###ads2b', // 11
            'example.*,wikipedia.com###ads2b', // 12
            '/ads2a-$domain=example.*|wikipedia.com', // 13
            '/ads2a-$domain=example.com', // 14
            '/ads2b-$domain=example.com', // 15
            '/ads2b-$domain=example.*|wikipedia.com', // 16
        ];
        $this->analyse($lines, [
            [2, 'Duplicate filter: identical to the filter rule on line 1'],
            [4, 'Redundant filter: domain example.com already covered on line 3'],
            [6, 'Redundant filter: domain example.com already covered on line 5'],
            [8, 'Redundant filter: domain example.com already covered on line 7'],

            [10, 'Duplicate filter: identical to the filter rule on line 9'],
            [11, 'Duplicate filter: identical to the filter rule on line 12'],
            [14, 'Redundant filter: domain example.com already covered on line 13'],
            [15, 'Redundant filter: domain example.com already covered on line 16'],
        ]);

        $lines = [
            '||example.com^$domain=example.com',
            '||example.com^$domain=ads.example.com|example.*',
            'example.com###ads1',
            'ads.example.com,example.*###ads1',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: domain example.com already covered on line 2'],
            [2, 'Redundant domain: ads.example.com is covered by example.*'],
            [3, 'Duplicate filter: identical to the filter rule on line 4'],
            [4, 'Redundant domain: ads.example.com is covered by example.*'],
        ]);
    }

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
            [1, 'Redundant filter: /ads$domain=~example.org already covered by /ads on line 2'],
            [3, 'Redundant filter: /ads$domain=example.com already covered by /ads on line 2'],
            [4, 'Redundant filter: ~example.com##.ads already covered by ##.ads on line 5'],
            [6, 'Redundant filter: example.org##.ads already covered by ##.ads on line 5'],
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
            [2, 'Redundant filter: /banner$domain=~example.org already covered by /banner on line 1'],
            [3, 'Redundant filter: /banner$domain=example.com already covered by /banner on line 1'],
        ]);

        $lines = [
            '##.ads',             // L1: Truly Global
            '~example.org##.ads', // L2: Almost Global
            'example.com##.ads',  // L3: Local
        ];

        // L3 should be considered redundant by L1, not L2.
        // L2 is also redundant by L1.
        $this->analyse($lines, [
            [2, 'Redundant filter: ~example.org##.ads already covered by ##.ads on line 1'],
            [3, 'Redundant filter: example.com##.ads already covered by ##.ads on line 1'],
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
            [2, 'Redundant filter: ##div[class="ads banner"] is redundant due to more general selector on line 1'],
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
            [1, 'Redundant filter: /ads-banner- already covered by /ads- on line 2'],
            [3, 'Redundant filter: /ads-banner-top already covered by /ads- on line 2'],
        ]);

        $lines = [
            '/ads',                          // L1: Truly Global
            '/ads$image',                    // L2: With options
            '/ads$image,domain=example.com', // L3: Target
        ];
        // L3 should be considered redundant by L1 (most common), not L2.
        // L2 is also redundant by L1.
        $this->analyse($lines, [
            [2, 'Redundant filter: /ads$image already covered by /ads on line 1'],
            [3, 'Redundant filter: /ads$image,domain=example.com already covered by /ads on line 1'],
        ]);
    }

    #[PHPUnit\Test]
    public function net_filter_1(): void
    {
        $lines = [
            'www.youtube.com',
            'youtube.com',
            'x.klarnacdn.net',
            'klarnacdn.net',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: www.youtube.com already covered by youtube.com on line 2'],
            [3, 'Redundant filter: x.klarnacdn.net already covered by klarnacdn.net on line 4'],
        ]);

        $lines = [
            '||ads1-adnow.com',
            '||adnow.com',
            '||click-cdn.com',
            '||ck-cdn.com',

            '||alitems.com',
            '||alitems.co',
            '||example.co^',
            '||example.com^',
        ];
        $this->analyse($lines);
        $lines = [
            'ads1-adnow.com',
            'adnow.com',
            'click-cdn.com',
            'ck-cdn.com',
            'alitems.com',
            'alitems.co',
            'keyvdowallet.me',
            't.me',
            'yandex.com',
            'ex.co',
            'ps.w.org',
            's.w.org',
        ];
        $this->analyse($lines);

        $this->analyse($lines);
        $lines = [
            'amazon.com.au',
            'amazon.com',
            'media-amazon.com',
            'm.media-amazon.com',
        ];
        $this->analyse($lines, [
            [4, 'Redundant filter: m.media-amazon.com already covered by media-amazon.com on line 3'],
        ]);
    }

    #[PHPUnit\Test]
    public function net_filter_2(): void
    {
        $lines = [
            '||example.com/path',
            '||example.com',

            '||example.org/path',
            '||example.org^',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: ||example.com/path already covered by ||example.com on line 2'],
            [3, 'Redundant filter: ||example.org/path already covered by ||example.org^ on line 4'],
        ]);

        $lines = [
            '@@||yandex.com^$generichide',
            '@@||yandex.com^',
            '@@||example.com^$shide',
            '@@||example.com/search?$generichide',

            '@@||example.org^$shide,badfilter',
            '@@||example.org/search?$shide',

            '@@||yahoo.com^$generichide',
            '@@||yahoo.com/search?$generichide',
        ];
        $this->analyse($lines, [
            [8, 'Redundant filter: @@||yahoo.com/search?$generichide already covered by @@||yahoo.com^ on line 7'],
        ]);
    }

    #[PHPUnit\Test]
    public function net_filter_3(): void
    {
        // https://github.com/easylist/easylist/blob/105e18723d/easylist/easylist_adservers.txt#L53164
        $lines = [
            '||example.com^',
            '|example.com^',
        ];
        $this->analyse($lines);
    }
}
