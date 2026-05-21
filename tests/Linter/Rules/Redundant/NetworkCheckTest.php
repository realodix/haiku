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
            '!',
            '*$domain=a.com',
            '*$domain=a.com',
        ];
        $this->analyse($lines, [
            [4, 'Redundant filter: ||example.org^$script already defined on line 2.'],
            [5, 'Redundant filter: ||example.com^ already defined on line 1.'],
            [8, 'Redundant filter: *$domain=a.com already defined on line 7.'],
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
            '||somesite.com^',
            '/banner-',
            '||somesite.com^*/banner-$image',
            '||somesite.com^*/path',

            '/banner2-',
            '||example.com^',
            '||example.com^*/banner2-$image',
            '||example.com^*/path',
        ];
        $this->analyse($lines, [
            [3, 'Redundant filter: ||somesite.com^*/banner-$image already covered by ||somesite.com^ on line 1.'],
            [4, 'Redundant filter: ||somesite.com^*/path already covered by ||somesite.com^ on line 1.'],
            [7, 'Redundant filter: ||example.com^*/banner2-$image already covered by /banner2- on line 5.'],
            [8, 'Redundant filter: ||example.com^*/path already covered by ||example.com^ on line 6.'],
        ]);

        $lines = [
            '/banner-$image,domain=x.com|y.com,css',
            '/banner-$image,css',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: /banner-$image,domain=x.com|y.com,css already covered by global filter on line 2.'],
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
    public function generic_redundancy_with_wildcard(): void
    {
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
            '||inc*-rev.static-cloudflare.workers.dev',
            '||inc-rev.static-cloudflare.workers.dev^',
            '||inc1-rev.static-cloudflare.workers.dev^',
            '||increase*-rev.static-cloudflare.workers.dev^',
            '||increase2-rev.static-cloudflare.workers.dev^',

            '||increase-rev*.static-cloudflare.workers.dev^',
            '||increase-rev1.static-cloudflare.workers.dev^',
            '||increase-rev2.static-cloudflare.workers.dev^',

            '||increase*rev.static-cloudflare.workers.dev^',
            '||increase1rev.static-cloudflare.workers.dev^',

            '||somesite1.com^',
            '||somesite*.com^',
            '||*.example.com^',
            '||x.example.com^',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: ||inc-rev.static-cloudflare.workers.dev^ already covered by ||inc*-rev.static-cloudflare.workers.dev on line 1.'],
            [3, 'Redundant filter: ||inc1-rev.static-cloudflare.workers.dev^ already covered by ||inc*-rev.static-cloudflare.workers.dev on line 1.'],
            [4, 'Redundant filter: ||increase*-rev.static-cloudflare.workers.dev^ already covered by ||inc*-rev.static-cloudflare.workers.dev on line 1.'],
            [5, 'Redundant filter: ||increase2-rev.static-cloudflare.workers.dev^ already covered by ||inc*-rev.static-cloudflare.workers.dev on line 1.'],
            [7, 'Redundant filter: ||increase-rev1.static-cloudflare.workers.dev^ already covered by ||increase-rev*.static-cloudflare.workers.dev^ on line 6.'],
            [8, 'Redundant filter: ||increase-rev2.static-cloudflare.workers.dev^ already covered by ||increase-rev*.static-cloudflare.workers.dev^ on line 6.'],
            [10, 'Redundant filter: ||increase1rev.static-cloudflare.workers.dev^ already covered by ||increase*rev.static-cloudflare.workers.dev^ on line 9.'],
            [11, 'Redundant filter: ||somesite1.com^ already covered by ||somesite*.com^ on line 12.'],
            [14, 'Redundant filter: ||x.example.com^ already covered by ||*.example.com^ on line 13.'],
        ]);
    }

    #[PHPUnit\Test]
    public function generic_redundancy_with_negated_domains(): void
    {
        $lines = [
            // Case 1: Global filter with options
            '/banner-$image,domain=~x.com|y.com,css',
            '/banner-$image,css',

            // Case 2: Global filter without options
            'adv',
            'adv$domain=~x.com',

            // Case 3: Global filter defined before (covered by Pass 1)
            '$removeparam=utm_referrer',
            '$to=~example.com,removeparam=utm_referrer',

            // Case 4: Multiple negated domains
            '||ads.com^',
            '||ads.com^$domain=~a.com|~b.com',

            // Case 5: Mix of negated and positive domains
            'test_rule',
            'test_rule$domain=~neg.com|pos.com',

            // Case 7: Mixed rules must be identical domains to cover each other (regex match)
            '/ads-m-c/*$domain=~example.org|example.com',
            '/ads-m-c/ads$domain=~example.org|example.com',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: /banner-$image,domain=~x.com|y.com,css already covered by global filter on line 2.'],
            [4, 'Redundant filter: adv$domain=~x.com already covered by adv on line 3.'],
            [6, 'Redundant filter: $to=~example.com,removeparam=utm_referrer already covered by global filter on line 5.'],
            [8, 'Redundant filter: ||ads.com^$domain=~a.com|~b.com already covered by ||ads.com^ on line 7.'],
            [10, 'Redundant filter: test_rule$domain=~neg.com|pos.com already covered by test_rule on line 9.'],
            [12, 'Redundant filter: /ads-m-c/ads$domain=~example.org|example.com already covered by global filter on line 11.'],
        ]);

        // Almost-global filter should cover local filter on a different domain
        $lines = [
            '/banner/*$domain=~example.net',
            '||example.org/banner/',
            '/banner-$domain=~example.org',
            '||somesite.com^*/banner-$image',
            '||a.com',
            '||a.com$domain=~example.net',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: ||example.org/banner/ already covered by /banner/* on line 1.'],
            [4, 'Redundant filter: ||somesite.com^*/banner-$image already covered by /banner- on line 3.'],
            [6, 'Redundant filter: ||a.com$domain=~example.net already covered by ||a.com on line 5.'],
        ]);

        // Almost-global filter should NOT cover global filter
        $lines = [
            '/ads/*$domain=~example.org',
            '/ads/*',

            '/banner/ads-',
            '/banner/*$domain=~example.org',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: /ads/*$domain=~example.org already covered by /ads/* on line 2.'],
        ]);

        $lines = [
            // Case 1: Almost-global filter should NOT cover local filter on its excluded domain
            '/banner-unique/*$domain=~example.org',
            '||example.org/banner-unique/*$image',

            // Case 2: Rules with different exclusions should NOT cover each other
            '/ads-diff-neg/*$domain=~example.org',
            '/ads-diff-neg/*$domain=~example.net',

            // Case 3: Mixed rules with different domains should NOT cover each other
            '/ads-m-d/*$domain=~example.org|example.com',
            '/ads-m-d/ads$domain=~example.org|example.net',
            '/ads-mix/*$domain=~example.org|example.com',
            '||example.com/ads-mix/',
        ];
        $this->analyse($lines);
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
            '*$to=a.com|b.com|~c.com',
            '*$to=a.com',
            '*$to=~c.com',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: *$to=a.com|b.com|~c.com already covered by global filter on line 3.'],
            [2, 'Redundant filter: *$to=a.com already covered by global filter on line 3.'],
            [3, 'Redundant filter: domain ~c.com already covered on line 1.'],
        ]);

        $lines = [
            '-banner-$image,domain=a.com|b.com',
            '-banner-$from=a.com,image',        // Redundant
            '-banner-$image,domain=a.com,css',
            '-banner-$image,domain=x.com',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: domain a.com already covered on line 1.'],
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
    public function case(): void
    {
        // case insensitive
        $lines = [
            '/ads/*',
            '/ADS/*',
            '/ADS1/*',
            '||somesite.com/ads1/',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: /ADS/* already defined on line 1.'],
            [4, 'Redundant filter: ||somesite.com/ads1/ already covered by /ads1/* on line 3.'],
        ]);

        $lines = [
            '||example.org^$script',
            '||example.org^$SCRIPT',
        ];
        $this->analyse($lines, [
            [2, 'Option "SCRIPT" must be lowercase.'],
            [2, 'Redundant filter: ||example.org^$SCRIPT already defined on line 1.'],
        ]);

        // case sensitive
        $lines = [
            '?url=http/$doc,to=com|io|net,match-case,urlskip=?url',
            '?URL=http/$doc,to=com|io|net,match-case,urlskip=?URL',
        ];
        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function respectBadfilter(): void
    {
        $lines = [
            '@@||github.io^$badfilter',
            '@@||github.io^',

            '||github.io^$badfilter',
            '||github.io^',
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
    public function net_typo(): void
    {
        // typo, appears as a regex
        $lines = [
            '/foo/bar/$image',
        ];
        $this->analyse($lines);

        // regex not valid
        // preg_match(): Compilation failed: missing opening brace after \o at offset 24
        // https://github.com/DandelionSprout/adfilt/blob/3a505745b/AntiRacismList.txt#L91-L92
        // https://github.com/realodix/haiku/blob/v1.13.6/src/Linter/Rules/Redundant/NetworkCheck.php#L402
        $lines = [
            '/^(.*\.|.*//)?n[eе]rdr\ot\ic\.c\om/?.*$/$all',
            '/^(.*\.|.*//)?p[aа]tre\on\.c\om/[eе]nd\ym\i\ontv/?.*$/$all',
        ];
        $this->analyse($lines);
    }
}
