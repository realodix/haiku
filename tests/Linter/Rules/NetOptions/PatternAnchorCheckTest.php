<?php

namespace Realodix\Haiku\Test\Linter\Rules\NetOptions;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class PatternAnchorCheckTest extends TestCase
{
    #[PHPUnit\Test]
    public function valid(): void
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
    public function not_valid(): void
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
    public function special_case(): void
    {
        $lines = [
            // https://github.com/easylist/ruadlist/blame/f20ff187db/advblock/whitelist.txt#L323
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
}
