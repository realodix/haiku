<?php

namespace Realodix\Haiku\Test\Unit;

trait GeneralProvider
{
    public static function notCombinedProvider(): array
    {
        return [
            // Network
            [
                ['/Ads.', '/ads.'],
                ['/Ads.', '/ads.'],
            ],
            [
                ['/Ads.$domain=/[a-z]/', '/ads.$domain=/[A-Z]/'],
                ['/Ads.$domain=/[a-z]/', '/ads.$domain=/[A-Z]/'],
            ],
            [
                ['/ads.$domain=a.com', '/ads.$from=a.com'],
                ['/ads.$domain=a.com', '/ads.$from=a.com'],
            ],
            [
                ['/Ads.', '/ads.', '/ads.$domain=a.com', '/ads.$from=a.com'],
                ['/Ads.', '/ads.', '/ads.$domain=a.com', '/ads.$from=a.com'],
            ],

            // Cosmetic
            [
                ['##.Ads', '##.ads'],
                ['##.Ads', '##.ads'],
            ],
            [
                ['a.com##.Ads', 'a.com##.ads'],
                ['a.com##.Ads', 'a.com##.ads'],
            ],
            [
                ['##.Ads', 'a.com##.Ads', '##.ads', 'a.com##.ads'],
                ['##.Ads', 'a.com##.Ads', '##.ads', 'a.com##.ads'],
            ],

            // Scriptlet
            [
                [
                    'example.com##+js(no-fetch-if, /Adsbygoogle.js$/ method:/HEAD|POST/)',
                    'example.com##+js(no-fetch-if, /adsbygoogle.js$/ method:/HEAD|POST/)',
                ],
                [
                    'example.com##+js(no-fetch-if, /Adsbygoogle.js$/ method:/HEAD|POST/)',
                    'example.com##+js(no-fetch-if, /adsbygoogle.js$/ method:/HEAD|POST/)',
                ],
            ],
        ];
    }
}
