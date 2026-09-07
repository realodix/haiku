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
