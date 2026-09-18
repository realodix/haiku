<?php

namespace Realodix\Haiku\Test\Linter\Rules;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class NetPatternCheckTest extends TestCase
{
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
            '||example.com^$csp=script-src \'none\'',
            '$csp=child-src \'none\'; frame-src \'self\' *',
            '||example.com^$replace=/foo bar/baz/',
            '*$header=response:set-cookie:x=c; path=/; max-age=21600',
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
