<?php

namespace Realodix\Haiku\Test\Linter\Rules\NetOptions;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\NetOptions\GeneralCheck;
use Realodix\Haiku\Test\TestCase;

class GeneralCheckTest extends TestCase
{
    #[PHPUnit\Test]
    public function case(): void
    {
        $lines = [
            '*$3p,SCRIPT,Css',
        ];

        $this->analyse($lines, [
            [1, 'Option "Css" must be lowercase.'],
            [1, 'Option "SCRIPT" must be lowercase.'],
        ]);
    }

    #[PHPUnit\Test]
    public function duplicate(): void
    {
        $lines = [
            '*$3p,script,3p',
            '*$3p,script,3p,script',
            '*$domain=a.com,domain=b.com',
        ];

        $this->analyse($lines, [
            [1, 'Duplicate option: $3p'],
            [2, 'Duplicate option: $3p'],
            [2, 'Duplicate option: $script'],
            [3, 'Duplicate option: $domain'],
        ]);
    }

    #[PHPUnit\Test]
    public function duplicateWithItsNegation(): void
    {
        $lines = [
            '*$script,~script',
            '*$css,~stylesheet',
            '*$stylesheet,~css',
        ];

        $this->analyse($lines, [
            [1, '$script conflicts with its negation.'],
            [2, '$stylesheet conflicts with its negation.'],
            [3, '$stylesheet conflicts with its negation.'],
        ]);
    }

    #[PHPUnit\Test]
    public function duplicateWithItsAlias(): void
    {
        $lines = [
            '*$script,domain=example.com,from=example.com',
            '*$css,script,stylesheet',
        ];

        $this->analyse($lines, [
            [1, 'Duplicate option: $from and $domain are aliases of each other.'],
            [2, 'Duplicate option: $css and $stylesheet are aliases of each other.'],
        ]);
    }

    #[PHPUnit\Test]
    public function invalidNegation(): void
    {
        $lines = [
            '*$~strict1p',
            '*$~strict3p',
            '*$~domain=a.com',
        ];

        $this->analyse($lines, [
            [1, '$strict1p cannot be negated.'],
            [2, '$strict3p cannot be negated.'],
            [3, '$domain cannot be negated.'],
        ]);
    }

    #[PHPUnit\Test]
    public function exceptionOnly(): void
    {
        $lines = [
            '@@*$important',
        ];

        $this->analyse($lines, [
            [1, 'Invalid filter: $important is not allowed in exception rules.'],
        ]);

        $lines = [
            '*$cname',
            '*$genericblock',
        ];

        $this->analyse($lines, [
            [1, 'Invalid filter: $cname is only allowed in exception rules.'],
            [2, 'Invalid filter: $genericblock is only allowed in exception rules.'],
        ]);

        $lines = [
            '*$important',

            '@@*$cname',
            '@@*$genericblock',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function withoutValue_exceptionOnly(): void
    {
        $lines = [
            '*$csp',
            '*$permissions',
            '*$redirect',
            '*$redirect-rule',
            '*$uritransform',
            '*$replace',
            '*$urlskip',
            '||example.org^$removeheader',
        ];

        $this->analyse($lines, [
            [1, 'Invalid filter: $csp without a value is only allowed as an exception rule.'],
            [2, 'Invalid filter: $permissions without a value is only allowed as an exception rule.'],
            [3, 'Invalid filter: $redirect without a value is only allowed as an exception rule.'],
            [4, 'Invalid filter: $redirect-rule without a value is only allowed as an exception rule.'],
            [5, 'Invalid filter: $uritransform without a value is only allowed as an exception rule.'],
            [6, 'Invalid filter: $replace without a value is only allowed as an exception rule.'],
            [7, 'Invalid filter: $urlskip without a value is only allowed as an exception rule.'],
            [8, 'Invalid filter: $removeheader without a value is only allowed as an exception rule.'],
        ]);

        $lines = [
            '@@*$csp=foo',
            '@@*$permissions',
            '@@*$redirect',
            '@@*$redirect-rule',
            '@@*$uritransform',
            '@@*$replace',
            '@@*$urlskip',
            '*$urlskip=foo',
            '@@||example.org^$removeheader',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function denyallow_value(): void
    {
        $lines = [
            '*$script,denyallow=x.com|~y.com|z.com,domain=a.com',
            '*$script,denyallow=x.com|y.*|z.com,domain=a.com',
            '*$script,denyallow=~foo.*,domain=a.com',
        ];

        $this->analyse($lines, [
            [1, 'Domains in the $denyallow value cannot be negated: "~y.com"'],
            [2, 'Domains in the $denyallow value cannot have a wildcard TLD: "y.*"'],
            [3, 'Domains in the $denyallow value cannot be negated: "~foo.*"'],
            [3, 'Domains in the $denyallow value cannot have a wildcard TLD: "~foo.*"'],
        ], [GeneralCheck::class]);
    }

    #[PHPUnit\Test]
    public function denyallow_and_to(): void
    {
        $lines = [
            '*$script,denyallow=x.com,domain=y.com,to=z.org',
        ];

        $this->analyse($lines, [
            [1, 'Invalid filter: $denyallow cannot be used together with $to.'],
        ]);
    }

    #[PHPUnit\Test]
    public function denyallow_requires_domain(): void
    {
        $lines = [
            '*$3p,script,denyallow=x.com|y.com,domain=a.com|b.com',
            '*$3p,script,denyallow=x.com',
        ];

        $this->analyse($lines, [
            [2, 'Invalid filter: $denyallow requires $domain.'],
        ], [GeneralCheck::class]);
    }

    #[PHPUnit\Test]
    public function deprecatedOptions(): void
    {
        $lines = [
            '||example.org^$empty',
            '||example.com/videos/$mp4',
            '||example.com^$queryprune=foo',
            '*$queryprune=utm_source',
        ];

        $this->analyse($lines, [
            [1, 'Deprecated: The filter option $empty is deprecated.'],
            [2, 'Deprecated: The filter option $mp4 is deprecated.'],
            [3, 'Deprecated: The filter option $queryprune is deprecated.'],
            [4, 'Deprecated: The filter option $queryprune is deprecated.'],
        ]);
    }

    #[PHPUnit\Test]
    public function possibly_invalid_options_marker(): void
    {
        $lines = [
            '||example.com^domain=x.com',
            // https://github.com/ABPindo/indonesianadblockrules/blob/9460206d53/src/adult/adult_specific_block.txt
            '||example.com^,domain=x.com',
            // https://github.com/AdguardTeam/AdguardFilters/blob/136526da7c/ChineseFilter/sections/antiadblock.txt#L146
            '@@||googleads.g.doubleclick.net/favicon.ico,domain=music.wandhi.com',
        ];
        $this->analyse($lines, [
            [1, 'Possibly missing "$" before the filter option.'],
            [2, 'Possibly missing "$" before the filter option.'],
            [3, 'Possibly missing "$" before the filter option.'],
        ]);

        $lines = [
            '/munin/a/tr/browserjs?domain=',
            '/counter/?domain=$image,~third-party',
            '@@||adservice.google.com/adsid/integrator.js?domain=www.cbs.com$domain=cbs.com',
            '||adservice.google.*/adsid/integrator.js?domain=dl.ccbluex.net$redirect=nooptext,important,domain=dl.ccbluex.net',
            '||cdn.jwplayer.com/*/playlists/*?page_domain=www.techwalla.com',
        ];
        $this->analyse($lines);

        $lines = [
            // https://github.com/easylist/easylist/blob/cd27c0c2b0/easylist_cookie/easylist_cookie_allowlist.txt#L95
            '@@||consent.truste.com/notice$domain=$domain=fortune.com',
            // https://github.com/ABPindo/indonesianadblockrules/blob/9460206d53/src/advert/specific_block.txt#L152
            '||bk21.net/*.gif$image$domain=juragan.film',
        ];
        $this->analyse($lines, [
            [1, 'Possibly multiple "$" before the filter option.'],
            [1, 'Bad domain: "$domain=fortune.com"'],
            [2, 'Possibly multiple "$" before the filter option.'],
        ]);

        $lines = [
            '/api.sportplus.watch\/v\d\.\d+\/\w+$/$xmlhttprequest,domain=sportplus.tv',
            '/\/\d+\.js$/$domain=7themes.su',
            '/GetVodPlaybackResources?$jsonprune=\$.vodPlaybackUrls.result.playbackUrls.cuepoints,xmlhttprequest,domain=amazon.com',
            '@@||alkalimetricsink-pa.clients6.google.com/$rpc/google.internal.alkali.applications.metricsink.v1.MetricService/RecordMetrics$domain=matrix.itasoftware.com,stealth=referrer',
        ];
        $this->analyse($lines);
    }
}
