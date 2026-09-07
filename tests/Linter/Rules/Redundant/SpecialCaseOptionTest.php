<?php

namespace Realodix\Haiku\Test\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class SpecialCaseOptionTest extends TestCase
{
    #[PHPUnit\Test]
    public function respectBadfilter(): void
    {
        $lines = [
            '@@||github.io^$badfilter',
            '@@||github.io^',

            '||github.io^$badfilter',
            '||github.io^',
        ];
        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function respectPopup(): void
    {
        $lines = [
            '/ads/*$popup',
            '/ads/*',
            '||example.com/ads/$popup',
            '||example.com^',
            '||example.com^$popup',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function respectThirdParty(): void
    {
        $lines = [
            '||example.com^$third-party',
            '||example.com^',
        ];
        $this->analyse($lines);

        $lines = [
            '||example.com^$~third-party',
            '||example.com^',
            '||example.org^$third-party',
            '||example.org^$third-party',

            '/mbp/pre/*$third-party,script',
            '/mbp/pre/*?sid=$script,third-party',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: ||example.com^$~third-party already covered by ||example.com^ on line 2'],
            [4, 'Duplicate filter: ||example.org^$third-party already defined on line 3'],
            [6, 'Redundant filter: /mbp/pre/*?sid=$script,third-party already covered by /mbp/pre/* on line 5'],
        ]);
    }

    #[PHPUnit\Test]
    public function respectCosmeticException(): void
    {
        $lines = [
            '##.ads',
            'example.com,example.org,example.site##.ads',
            '@@||example.org^$ghide',
            'x.com##.ads',
            '@@||x.com^$ghide',
            'y.com##.ads',
            'z.com##.ads',
            '@@*$generichide,domain=y.com|z.com',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: domain example.com already covered on line 1'],
            [2, 'Redundant filter: domain example.site already covered on line 1'],
        ]);

        $lines = [
            '##.ads',
            'example.com,example.org,example.site##.ads',
            '@@||example.org^$ehide',
            'x.com##.ads',
            '@@||x.com^$ghide',
            'y.com##.ads',
            'z.com##.ads',
            '@@*$elemhide,domain=y.com|z.com',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: domain example.com already covered on line 1'],
            [2, 'Redundant filter: domain example.site already covered on line 1'],
        ]);
    }
}
