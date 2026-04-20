<?php

namespace Realodix\Haiku\Test\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\Redundant\NetworkCheck;
use Realodix\Haiku\Test\TestCase;

class NetworkCheckTest extends TestCase
{
    private const RULE = [NetworkCheck::class];

    #[PHPUnit\Test]
    public function exact_duplicate(): void
    {
        $lines = [
            '||example.com^',
            '||example.org^$script',
            '!',
            '||example.org^$script',
            '||example.com^',
        ];
        $this->analyse($lines, [
            [4, 'Redundant filter: ||example.org^$script already defined on line 2.'],
            [5, 'Redundant filter: ||example.com^ already defined on line 1.'],
        ], self::RULE);

        // case insensitive
        $lines = [
            '/ads/*',
            '/Ads/*',
            '||example.org^$script',
            '||example.org^$SCRIPT',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: /Ads/* already defined on line 1.'],
            [4, 'Redundant filter: ||example.org^$SCRIPT already defined on line 3.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function generic_redundancy(): void
    {
        $lines = [
            '/ads/*',
            '||somesite.com/ads/',
            '/banner-',
            '||somesite.com^*/banner-$image',
            '!',
            '/banner/ads-',
            '/banner/*',
            '/banner2/ads-$image',
            '/banner2/*',
            '/ads-$image,domain=a.com|b.com',
            '/ads-',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: ||somesite.com/ads/ already covered by /ads/* on line 1.'],
            [4, 'Redundant filter: ||somesite.com^*/banner-$image already covered by /banner- on line 3.'],
            [6, 'Redundant filter: /banner/ads- already covered by /banner/* on line 7.'],
            [8, 'Redundant filter: /banner2/ads-$image already covered by /banner2/* on line 9.'],
            [10, 'Redundant filter: /ads-$image,domain=a.com|b.com already covered by /ads- on line 11.'],
        ]);

        $lines = [
            '/banner-$image,domain=x.com|y.com,css',
            '/banner-$image,css',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: /banner-$image,domain=x.com|y.com,css already covered by global filter on line 2.'],
        ]);

        $lines = [
            '||example.org/wp-content/uploads/*/google-play-spoticatolico.png',
            '||example.org/wp-content/uploads/2020/10/google-play-spoticatolico.png$domain=example.com',
            '||secure---sso---robinhood---com-cdn.webflow.io^$doc',
            '-sso-*.webflow.io^',
            '/mailer.hostinger.io/o/*$image',
            '/mailer.*/o/*$image',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: ||example.org/wp-content/uploads/2020/10/google-play-spoticatolico.png$domain=ex... already covered by ||example.org/wp-content/uploads/*/google-play-spoticatolico.png on line 1.'],
            [3, 'Redundant filter: ||secure---sso---robinhood---com-cdn.webflow.io^$doc already covered by -sso-*.webflow.io^ on line 4.'],
            [5, 'Redundant filter: /mailer.hostinger.io/o/*$image already covered by /mailer.*/o/* on line 6.'],
        ]);

        $lines = [
            '@@/adv/ads',
            '@@/adv/*',
            '/adv/ads',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: @@/adv/ads already covered by @@/adv/* on line 2.'],
        ]);

        $lines = [
            '/ads/*$image',
            '||somesite.com/ads/$image',
            '!',
            '/banner/ads-',
            '/banner/*$image',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: ||somesite.com/ads/$image already covered by /ads/* on line 1.'],
        ]);
    }

    #[PHPUnit\Test]
    public function generic_redundancy_by_regex(): void
    {
        $lines = [
            '/advertisement/ads-$image',
            '/advertisement/*$image',
            '/adv/$image',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: /advertisement/ads-$image already covered by /adv/ on line 3.'],
            [2, 'Redundant filter: /advertisement/*$image already covered by /adv/ on line 3.'],
        ]);

        $lines = [
            '/ads.gif|$image',
            '/\.(gif|webp)/',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: /ads.gif|$image already covered by /\.(gif|webp)/ on line 2.'],
        ]);
    }

    #[PHPUnit\Test]
    public function different_options_order(): void
    {
        $lines = [
            '*$image,script',
            '*$script,image',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: *$script,image already defined on line 1.'],
        ]);
    }

    #[PHPUnit\Test]
    public function domainRedundancy(): void
    {
        $lines = [
            '*$to=a.com|b.com',
            '*$to=a.com',
        ];
        $this->analyse($lines, [
            [2, "Redundant filter: domain 'a.com' already covered on line 1."],
        ]);

        $lines = [
            '-banner-$image,domain=a.com|b.com',
            '-banner-$from=a.com,image',        // Redundant
            '-banner-$image,domain=a.com,css',
            '-banner-$image,domain=x.com',
        ];
        $this->analyse($lines, [
            [2, "Redundant filter: domain 'a.com' already covered on line 1."],
        ]);
    }

    #[PHPUnit\Test]
    public function respectMixedContext(): void
    {
        $lines = [
            '-banner-$image,domain=a.com',
            '-banner-$image,to=a.com',
            '-banner-$image,domain=x.com,denyallow=a.com',
        ];

        // Should NOT report redundancy because contexts are different
        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function respectMatchCaseOption(): void
    {
        $lines = [
            '?url=http/$doc,to=com|io|net,match-case,urlskip=?url',
            '?URL=http/$doc,to=com|io|net,match-case,urlskip=?URL',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function respectPopupOption(): void
    {
        $lines = [
            '/ads/*$popup',
            '/ads/*',
            '||example.com/ads/$popup',
            '||example.com^',
            '||example.com^$popup',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function generic_redundancy_priority(): void
    {
        // assert 1
        $lines = [
            '/advertisement/ads-$image',
            '/adv/$image',
            '/advertisement/*$image',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: /advertisement/ads-$image already covered by /adv/ on line 2.'],
            [3, 'Redundant filter: /advertisement/*$image already covered by /adv/ on line 2.'],
        ]);

        // assert 2
        $lines = [
            '/advertisement/ads-$image',
            '/advertisement/*$image',
            '/adv/',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: /advertisement/ads-$image already covered by /adv/ on line 3.'],
            [2, 'Redundant filter: /advertisement/*$image already covered by /adv/ on line 3.'],
        ]);

        // assert 3
        $lines = [
            '/advertisement/ads-$image',
            '/advertisement/*$image',
            '/adv/$image',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: /advertisement/ads-$image already covered by /adv/ on line 3.'],
            [2, 'Redundant filter: /advertisement/*$image already covered by /adv/ on line 3.'],
        ]);
    }

    #[PHPUnit\Test]
    public function net_typo_as_regex(): void
    {
        // typo, appears as a regex
        $lines = [
            '/foo/bar/$image',
        ];
        $this->analyse($lines);
    }
}
