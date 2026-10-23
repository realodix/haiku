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
    public function likePathOrRegex(): void
    {
        $lines = [
            '/abc/',

            '/[0-9]+/',
            '/https:\/\/(www\.)?[a-z\d\.]+\/\?id=/',
            // https://github.com/AdguardTeam/AdguardFilters/blob/83e5d4cfe1/SpywareFilter/sections/general_extensions.txt#L433
            '/.*/',
        ];
        $this->analyse($lines, [
            [1, 'Possible path pattern in regex format.'],
        ], onlyRules: [NetPatternCheck::class]);

        $lines = [
            '/[0-9]+/*',
            '/foo?/*',

            '/abc/*',
            // https://github.com/easylist/ruadlist/blob/353c040553/advblock/general_block.txt#L345
            '/play/?ref=*/play/*$third-party,subdocument',
            // https://github.com/AdguardTeam/AdguardFilters/blob/e56a874505/SpywareFilter/sections/general_url.txt#L566
            '/logger/p.gif?*d=/*',
            // https://github.com/AdguardTeam/AdguardFilters/blob/f26115209b/SpywareFilter/sections/specific.txt#L6203
            '/static/cache.php?url=https://mc.yandex.com/metrika/*',
        ];
        $this->analyse($lines, [
            [1, 'Possible regex pattern in path format.'],
            [2, 'Possible regex pattern in path format.'],
        ]);
    }

    #[PHPUnit\Test]
    public function unnecessaryWildcard(): void
    {
        $lines = [
            '*/ads/*',
            '**/ads-*',
            '*/ads-**',
            '**/ads-**',

            '@@**/ads-**$css',
        ];
        $this->analyse($lines, [
            [1, 'Unnecessary leading wildcard.'],
            [2, 'Unnecessary leading wildcard.'],
            [3, 'Unnecessary trailing wildcard.'],
            [4, 'Unnecessary leading and trailing wildcards.'],
            [5, 'Unnecessary leading and trailing wildcards.'],
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
