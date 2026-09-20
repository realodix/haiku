<?php

namespace Realodix\Haiku\Test\Linter\Rules;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Test\TestCase;

class NetPatternCheckTest extends TestCase
{
    #[PHPUnit\Test]
    public function too_short_line(): void
    {
        app(LinterConfig::class)->rules = ['no_short_rules' => 5];
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
    public function checkSpaceInPattern(): void
    {
        $lines = [
            '||exa mple.org^',
            '||exa mple.com^$third-party',
            'exa mple.com^',
            '@@||exa mple.com^',
            '|http://face book.com',
        ];
        $this->analyse($lines, [
            [1, 'Net pattern should not contain spaces.'],
            [2, 'Net pattern should not contain spaces.'],
            [3, 'Net pattern should not contain spaces.'],
            [4, 'Net pattern should not contain spaces.'],
            [5, 'Net pattern should not contain spaces.'],
        ]);

        $lines = [
            '[Adblock Plus 2.0]',
            '[uBlock Origin]',
            'no-large-media: behind-the-scene false',
            'behind-the-scene * * noop',
            '||example.com^$csp=script-src \'none\'',
            '$csp=child-src \'none\'; frame-src \'self\' *',
            '||example.com^$replace=/foo bar/baz/',
            '*$header=response:set-cookie:x=c; path=/; max-age=21600',
            '/reg ex/$script,third-party,match-case',
        ];
        $this->analyse($lines);

        // uBlacklist
        $lines = [
            'url *= "example"',
            'title ^= "Something"',
            'title =~ /example domain/i',
            'scheme="http"',
            '!scheme="https"',
            '$category = "images" & host = "www.amazon.com"',
        ];
        $this->analyse($lines);
    }
}
