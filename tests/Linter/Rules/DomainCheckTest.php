<?php

namespace Realodix\Haiku\Test\Linter\Rules;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\DomainCheck;
use Realodix\Haiku\Test\TestCase;

class DomainCheckTest extends TestCase
{
    private const RULE = [
        DomainCheck::class,
    ];

    #[PHPUnit\Test]
    public function empty_domain(): void
    {
        $lines = [
            '##.ads',  // OK
            'example.com,example.org##.ads',  // OK
            ',example.com##.ads',             // Empty start
            'example.com,,example.org##.ads', // Empty middle
            'example.com,##.ads',             // Trailing comma
            '||ex.com^$domain=|a.com',        // Empty network option
        ];
        $this->analyse($lines, [
            [3, 'Unexpected empty domain before "example.com"'],
            [4, 'Unexpected empty domain between "example.com" and "example.org"'],
            [5, 'Unexpected empty domain after "example.com"'],
            [6, 'Unexpected empty domain before "a.com"'],
        ], self::RULE);

        $lines = [
            ',a.com,b.com,c.com,,d.com,e.com,f.com,,,g.com,h.com,i.com,##.ad-middle',
        ];
        $this->analyse($lines, [
            [1, 'Unexpected empty domain after "f.com"'],
            [1, 'Unexpected empty domain after "i.com"'],
            [1, 'Unexpected empty domain before "a.com"'],
            [1, 'Unexpected empty domain before "g.com"'],
            [1, 'Unexpected empty domain between "c.com" and "d.com"'],
        ], self::RULE);

        $lines = [
            ',##.ads',
            ',,##.ads',
            '*$domain=|',
        ];
        $this->analyse($lines, [
            [1, 'Invalid filter.'],
            [2, 'Invalid filter.'],
            [3, 'Invalid filter.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function bad_domain(): void
    {
        $lines = [
            '*$domain=*',
            // just wildcard
            '*##.ad',
            // path-in-domain syntax
            // https://adguard.com/kb/general/ad-filtering/create-own-filters/#path-in-domain-syntax
            'news.site.com/path##.sidebar-ad',
            'domain1.com,example.org/path##.banner',
        ];
        $this->analyse($lines, [
            [1, 'Bad domain: "*"'],
        ]);

        $lines = [
            'a,example.com,c##.ads',
            '*$domain=a|example.com|c',
            'example.##.ad',
            'e xample.com##.ad',
        ];
        $this->analyse($lines, [
            [1, 'Bad domain: "a"'],
            [1, 'Bad domain: "c"'],
            [2, 'Bad domain: "a"'],
            [2, 'Bad domain: "c"'],
            [3, 'Bad domain: "example."'],
            [4, 'Bad domain: "e xample.com" contains unnecessary whitespace.'],
        ]);

        $lines = [
            '*$domain=example.',
            '*$domain=0.0.0.',
            '!',
            '*$domain=/domain\.com/',
            '*$domain=/domain.com',
            '*$domain=.domain.com',
            '*$domain=domain.com/',
        ];
        $this->analyse($lines, [
            [1, 'Bad domain: "example."'],
            [5, 'Bad domain: "/domain.com"'],
            [6, 'Bad domain: ".domain.com"'],
            [7, 'Bad domain: "domain.com/"'],
        ]);

        $lines = [
            '[$domain=/example.net/]##.ad-branding',
            // contain noop option
            '*$script,third-party,denyallow=x.com,_____,domain=example.com',
            // https://github.com/easylist/easylist/commit/05b4a59ff6d6eeb00c044b05132d3817ca42bd7f
            // https://github.com/easylist/easylist/blob/d8630ca8f9/easylist/easylist_allowlist.txt#L175
            '@@||lastpass.com/ads.php$subdocument,domain=chrome-extension-scheme|moz-extension-scheme|addons.about-scheme',
            // https://github.com/uBlockOrigin/uAssets/blob/3bf1b42bd6/filters/filters-2024.txt#L3763
            '*$doc,ipaddress=199.59.243.227,to=~parked.domain',
        ];
        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function bad_tld_or_domain(): void
    {
        $lines = [
            'example##.ad',
        ];
        $this->analyse($lines, [
            [1, 'Bad domain: "example" is an invalid TLD'],
        ]);

        $lines = [
            '3v4l##.ad', // 3v4l.org
            'laravel-news##.ad', // laravel-news.com
        ];
        $this->analyse($lines, [
            [1, 'Bad domain: "3v4l"'],
            [2, 'Bad domain: "laravel-news"'],
        ]);

        $lines = [
            'example.coms##.ad',
            'example.or##.ad',
        ];
        $this->analyse($lines, [
            [1, 'Bad domain: "example.coms" has an invalid TLD'],
            [2, 'Bad domain: "example.or" has an invalid TLD'],
        ]);

        $lines = [
            'localhost##.ad',
            'local##.ad',
            'me##.ad',
            'example.*##.ad',
            '*$domain=0.0.0.0',
            '*$domain=/abc\.bar/',
        ];
        $this->analyse($lines, []);
    }

    #[PHPUnit\Test]
    public function bad_tld_special(): void
    {
        // Non ascii domain
        $lines = [
            // https://github.com/AdguardTeam/AdguardFilters/blob/378a138ac0/CyrillicFilters/RussianFilter/sections/specific.txt#L7850
            'скачатьофис.рф##a[href^="https://active-keys.ru/"]',
        ];
        $this->analyse($lines);

        // iana (special use)
        $lines = [
            // https://github.com/AdguardTeam/AdguardFilters/blob/378a138ac0/BaseFilter/sections/allowlist.txt#L2162
            'example.onion##.ads',
            // https://github.com/AdguardTeam/AdguardFilters/commit/f76f2fc392fdd69101fc2a74da03f3e59aea0486
            '@@||api.ip.sb/geoip$domain=tauri.localhost',
        ];
        $this->analyse($lines);

        // others
        $lines = [
            // https://github.com/AdguardTeam/AdguardFilters/commit/585fd71ca0568b94fcba6e7b102462017a4dcc33
            // https://github.com/AdguardTeam/AdguardFilters/commit/f7ad8a011f75559da68a3192f1990ae2d5a283fb
            '||m.yap.yahoo.com^$domain=~not.dns',
            // https://github.com/AdguardTeam/AdguardFilters/blob/378a138ac0/CyrillicFilters/RussianFilter/sections/general_extensions.txt#L1325
            'pornolab.lib,rutracker.lib##.ads',
            // https://github.com/easylist/easylist/blob/9c1013d0d5/easyprivacy/easyprivacy_thirdparty_international.txt#L3
            // https://github.com/easylist/easylist/blob/9c1013d0d5/easyprivacy/easyprivacy_thirdparty_international.txt#L655
            '||example.com^$domain=~fc2.jp.ip|~sprueche-zitate.net.ip',
        ];
        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function bad_domain_contains_whitespaces(): void
    {
        $lines = [
            '/single$domain= example.com ',
            '/foo$domain=example.com | example.org',
            '/bar$domain=example.com| example.org',
            '/baz$domain=example.com |example.org',
            'example.com , example.org##foo',
            'example.com, example.org##bar',
            'example.com ,example.org##baz',

            '*$domain=exampl e.',
        ];

        $this->analyse($lines, [
            [1, 'Bad domain: " example.com" contains unnecessary whitespace.'],
            [2, 'Bad domain: "example.com " contains unnecessary whitespace.'],
            [2, 'Bad domain: " example.org" contains unnecessary whitespace.'],
            [3, 'Bad domain: " example.org" contains unnecessary whitespace.'],
            [4, 'Bad domain: "example.com " contains unnecessary whitespace.'],
            [5, 'Bad domain: "example.com " contains unnecessary whitespace.'],
            [5, 'Bad domain: " example.org" contains unnecessary whitespace.'],
            [6, 'Bad domain: " example.org" contains unnecessary whitespace.'],
            [7, 'Bad domain: "example.com " contains unnecessary whitespace.'],
            [8, 'Bad domain: "exampl e." contains unnecessary whitespace.'],
        ]);
    }

    #[PHPUnit\Test]
    public function ancestorContexts(): void
    {
        $lines = [
            'example.com>>##.ads',
            'example.com>>##+js(set, iAmEmbeddedInExampleDotCom, true)',
        ];
        $this->analyse($lines);

        $lines = [
            'example.com>##.ads',
            'example.com>>>##.ads',
        ];
        $this->analyse($lines, [
            [1, 'Bad domain: "example.com>"'],
            [2, 'Bad domain: "example.com>>>"'],
        ]);

        $lines = [
            '*$domain=example.com>>',
        ];
        $this->analyse($lines, [
            [1, 'Bad domain: "example.com>>". The network filter does not support ancestor context.'],
        ]);
    }

    #[PHPUnit\Test]
    public function lowercase_domain_only(): void
    {
        $lines = [
            'Example.com,example.org,X.COM##.ads',
            '*$domain=Example.com|example.org|~X.COM',
        ];

        $this->analyse($lines, [
            [1, 'Domain Example.com must be lowercase.'],
            [1, 'Domain X.COM must be lowercase.'],
            [2, 'Domain Example.com must be lowercase.'],
            [2, 'Domain ~X.COM must be lowercase.'],
        ]);
    }

    #[PHPUnit\Test]
    public function duplicate_domain(): void
    {
        $lines = [
            'example.com,example.org,example.com##.ads',
            '*$domain=example.com|example.org|example.com',
            '~example.com,~example.org,~example.com##.ads',
        ];

        $this->analyse($lines, [
            [1, 'Duplicate domain: example.com'],
            [2, 'Duplicate domain: example.com'],
            [3, 'Duplicate domain: ~example.com'],
        ]);
    }

    #[PHPUnit\Test]
    public function contradictory_domain(): void
    {
        $lines = [
            'example.com,example.org,~example.com##.ads',
            '~example.com,example.org,example.com##.ads',
            '*$domain=example.com|example.org|~example.com',
        ];

        $this->analyse($lines, [
            [1, 'Contradictory domain example.com detected.'],
            [2, 'Contradictory domain example.com detected.'],
            [3, 'Contradictory domain example.com detected.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function regex_domain_is_skipped(): void
    {
        // Currently we skip regex domains to avoid complex comma handling
        $lines = [
            '||example.com^$domain=/regex{1,3}/',
            '/a,b/##.ads',
        ];

        $this->analyse($lines, []);
    }
}
