<?php

namespace Realodix\Haiku\Test\Linter\Rules;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Test\TestCase;

class ScriptletCheckTest extends TestCase
{
    #[PHPUnit\Test]
    public function sl_name(): void
    {
        $lines = [
            'example.org##+js(nowoif)',
            'example.org##+js(nowoif.js)',
            'example.org##+js( nowoif )',
            'example.org##+js("nowoif")',
            "example.org##+js('nowoif')",

            'example.org##+js(google-ima)',
            'example.org##+js(google-ima.js)',
            'example.org##+js(google-ima3)',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function unknown_name(): void
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
            [1, 'Invalid scriptlet: bar'],
            [2, 'Invalid scriptlet: bar'],
            [3, 'Invalid scriptlet: bar'],
            [4, 'Invalid scriptlet: nowolf'],
            [5, 'Invalid scriptlet: nowolf'],
        ]);

        app(LinterConfig::class)->rules = [
            'no_invalid_scriptlets' => ['known' => ['foo']],
        ];
        $lines = [
            'example.org##+js(foo)',
        ];
        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function trusted_and_custom_name(): void
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
    public function deprecated(): void
    {
        $lines = [
            'example.org##+js(csp)',
            'example.org##+js(csp.js)',
        ];

        $this->analyse($lines, [
            [1, 'Deprecated: Scriptlet csp is deprecated.'],
            [2, 'Deprecated: Scriptlet csp is deprecated.'],
        ]);
    }

    #[PHPUnit\Test]
    public function invalid_value(): void
    {
        $lines = [
            'example.com##+js(set, Object.keys, foo)',
            'example.com##+js(set-cookie, CookieConsent, foo)',
            'example.com##+js(set-local-storage-item, player.live.current.mute, foo)',
        ];
        $this->analyse($lines, [
            [1, 'Invalid set-constant value: foo'],
            [2, 'Invalid set-cookie value: foo'],
            [3, 'Invalid set-local-storage-item value: foo'],
        ]);

        $lines = [
            // case
            'example.com##+js(set, fooCase, "trueFunc")',

            // integer
            'example.com##+js(set, fooInt, 1)',
            'example.com##+js(set-cookie, fooInt, 1)',
            'example.com##+js(set-local-storage-item, fooInt, 1)',

            // empty
            "example.com##+js(set, CookieConsent, '')",
            "example.com##+js(set, document.body.oncopy, '', runAt)",
            "example.com##+js(set-local-storage-item, foo, '')",
            // https://github.com/uBlockOrigin/uAssets/blob/24e882aa5d/filters/filters-2023.txt#L3129
            'trakt.tv##+js(set, art3m1sItemNames.affiliate-wrapper, \'""\')',
            // https://github.com/uBlockOrigin/uAssets/blob/24e882aa5d/filters/filters-2026.txt#L725
            'uniquestream.net##+js(set, __US_CONFIG__.ads.urls)',

            // others
            "example.com##+js(acis, foo, '')",
            'example.com##+js(acis, foo, )',
            // https://github.com/uBlockOrigin/uAssets/blob/24e882aa5d/filters/annoyances-cookies.txt#L399
            'hatch.co##+js(set-local-storage-item, COOKIE_CONSENT_STATUS_4124, \'"dismissed"\', aa)',
        ];
        $this->analyse($lines);

        // requires a value
        $lines = [
            // https://github.com/easylist/easylist/blob/ea218bd35d/easylist_cookie/easylist_cookie_specific_uBO.txt#L848
            // We should get an empty-string, not `'reload` instead.
            'vandemoortel.de##+js(set-cookie, cookieConsent_given, , reload, 1)',
        ];
        $this->analyse($lines, [
            [1, 'Invalid set-cookie value: '],
        ]);
    }
}
