<?php

namespace Realodix\Haiku\Test\Linter\Rules;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Test\TestCase;

class ScriptletCheckTest extends TestCase
{
    #[PHPUnit\Test]
    public function parameter(): void
    {
        $lines = [
            'example.org##+js(nowoif)',
            'example.org##+js(nowoif.js)',
            'example.org##+js( nowoif )',
            "example.org##+js('nowoif')",
            'example.org##+js("nowoif")',

            'example.org##+js(google-ima)',
            'example.org##+js(google-ima.js)',
            'example.org##+js(google-ima3)',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function unknown_parameter(): void
    {
        $lines = [
            'example.org##+js(bar)',
            'example.org##+js("bar")',
            'example.org##+js( bar.js  )',
            // typo
            'example.org##+js(nowolf)',
            'example.org##+js(nowolf.js)',
        ];

        $this->analyse($lines, [
            [1, 'Unknown scriptlet: bar'],
            [2, 'Unknown scriptlet: bar'],
            [3, 'Unknown scriptlet: bar'],
            [4, 'Unknown scriptlet: nowolf'],
            [5, 'Unknown scriptlet: nowolf'],
        ]);

        app(LinterConfig::class)->rules = [
            'scriptlet_unknown' => ['known' => ['foo']],
        ];
        $lines = [
            'example.org##+js(foo)',
        ];
        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function trusted_and_custom_parameter(): void
    {
        $lines = [
            'example.org##+js(trusted-something)',
            'example.org##+js(trusted-something.js)',
            // https://github.com/brave/adblock-lists/blob/81ca1eac58/brave-lists/brave-specific.txt#L139
            'youtube.*##+js(brave-youtube-theater-fix)',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function deprecated_parameter(): void
    {
        $lines = [
            'example.org##+js(csp)',
            'example.org##+js(csp.js)',
        ];

        $this->analyse($lines, [
            [1, 'Deprecated scriptlet: csp'],
            [2, 'Deprecated scriptlet: csp'],
        ]);
    }
}
