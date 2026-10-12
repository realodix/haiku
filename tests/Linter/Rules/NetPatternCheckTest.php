<?php

namespace Realodix\Haiku\Test\Linter\Rules;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Linter\Rules\NetPatternCheck;
use Realodix\Haiku\Test\TestCase;

class NetPatternCheckTest extends TestCase
{
    #[PHPUnit\Test]
    public function too_short_line(): void
    {
        app(LinterConfig::class)->rules = ['no_short_rules' => ['minLen' => 5]];
        $lines = [
            'bar',    // Too short (3 < 5)
            'foo$css,3p', // Stripped to 'foo', too short
            '   xyz ', // Stripped to 'xyz', too short

            'abcde',   // OK (5 >= 5)
            '!a',    // Comment, OK
            '*$script,3p,denyallow=fastly.net|fastlylb.net|jquery.com|hwcdn.net|hcaptcha.com|recaptcha.net|cloudflare.com|cloudflare.net|google.com|googleapis.com|gstatic.com,domain=13x4.com',
        ];
        $this->analyse($lines, [
            [1, 'The rule is too short (under 5 characters).'],
            [2, 'The rule is too short (under 5 characters).'],
            [3, 'The rule is too short (under 5 characters).'],
        ]);

        $lines = [
            '$doc,domain=example.com',
            '*$script,3p,denyallow=google.com|googleapis.com|gstatic.com,domain=13x4.com',
            '@@*$ghide,domain=timesnownews.com',

            '[$domain=/example.net/]##.ad-branding',
            '$$advertisement-module',
            'example.com$$div:contains("Sponsored by")',
        ];
        $this->analyse($lines);

        app(LinterConfig::class)->rules = ['no_short_rules' => false];
        $this->analyse(['foo']);
    }

    #[PHPUnit\Test]
    public function domainAnchor_valid(): void
    {
        $lines = [
            '|https://example.com',
            '||example.com',
            '@@||pagead2.googlesyndication.com/pagead/js/adsbygoogle.js',
            '@@||example.com/js/pop.js|',
            '@@/js/ads.js|$script',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function domainAnchor_notValid(): void
    {
        $lines = [
            '|||https://example.com',
            '@@|||example.com/js/pop.js||',
            '@@/js/ads.js||$script',
        ];

        $this->analyse($lines, [
            [1, 'Too many "|" at the beginning (max 2 allowed).'],
            [2, 'Too many "|" at the beginning (max 2 allowed).'],
            [2, 'Too many "|" at the end (only 1 allowed).'],
            [3, 'Too many "|" at the end (only 1 allowed).'],
        ]);
    }

    #[PHPUnit\Test]
    public function domainAnchor_specialCase(): void
    {
        $lines = [
            // https://github.com/easylist/ruadlist/blob/f19edb909a/advblock/whitelist.txt#L291
            '@@||$domain=example.com', // valid
            '@@|||$domain=example.com',

            '||$domain=example.com', // valid
            '|||$domain=example.com',
        ];

        $this->analyse($lines, [
            [2, 'Too many "|" at the beginning (max 2 allowed).'],
            [4, 'Too many "|" at the beginning (max 2 allowed).'],
        ]);
    }

    #[PHPUnit\Test]
    public function unnecessaryWildcard(): void
    {
        $lines = [
            '*/banner123/*',
        ];
        $this->analyse($lines, [
            [1, 'Unnecessary leading wildcard.'],
        ], onlyRules: [NetPatternCheck::class]);

        $lines = [
            '*example.com*', // leading and trailing        // 1
            '*example.org^', // leading                     // 2
            '@@*/banner/*/', // leading                     // 4
            '||foo.*$all',   // trailing                    // 5
            '@@*xample.com***', // leading and trailing     // 6

            // Preserve
            '*$domain=example.com',                         // 7
            '/banner123/*',                                 // 8
            '@@/ads/*',                                     // 9
            'img.gif|*',                                    // 10
            // '||assets.bilsyndication.com/plugins/*',        // 11
            // '@@||1bit.space/default/public/assets/*',       // 12
            // Some people usually add `*` in front to avoid being considered a comment
            '*#*/ad/$image,redirect-rule=1x1.gif',          // 13
        ];
        $this->analyse($lines, [
            // [1, 'Unnecessary leading and trailing wildcards.'],
            // [2, 'Unnecessary leading wildcard.'],
            // [4, 'Unnecessary leading wildcard.'],
            // [5, 'Unnecessary trailing wildcard.'],
            // [6, 'Unnecessary leading and trailing wildcards.'],
            // [10, 'Unnecessary trailing wildcard.'],
        ], onlyRules: [NetPatternCheck::class]);

        $lines = [
            // uBlacklist
            '*://*.be/*',
            '@*://*.diplomatie.be/*',
            // Syntax error, we'll leave it as is
            '*',
        ];
        $this->analyse($lines, onlyRules: [NetPatternCheck::class]);
    }
}
