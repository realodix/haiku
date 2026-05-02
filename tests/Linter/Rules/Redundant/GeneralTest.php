<?php

namespace Realodix\Haiku\Test\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\Redundant\CosmeticCheck;
use Realodix\Haiku\Linter\Rules\Redundant\NetworkCheck;
use Realodix\Haiku\Test\TestCase;

class GeneralTest extends TestCase
{
    private const RULE = [
        CosmeticCheck::class,
        NetworkCheck::class,
    ];

    #[PHPUnit\Test]
    public function redundant_1(): void
    {
        $lines = [
            'example.com,example.org,x.com##.ads',
            'x.com,y.com##.ads',
            'example.com##.ads',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: domain x.com already covered on line 1.'],
            [3, 'Redundant filter: example.com##.ads already covered by ##.ads on line 1.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function redundant_2(): void
    {
        $lines = [
            '~example.org##.banner',
            'example.com##.banner',

            '~example.org,example.com##.ads',
            'example.com##.ads',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: example.com##.banner already covered by ##.banner on line 1.'],
        ], self::RULE);

        $lines = [
            '##img[alt="banner"]',
            '~example.org##img[alt*="Bann" i]',

            '##img[alt="advertising"]',
            '~example.org,example.com##img[alt^="adv"]',
        ];

        $this->analyse($lines, [
            [1, 'Redundant filter: ##img[alt="banner"] is redundant due to more general selector on line 2.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function redundant_3(): void
    {
        $lines = [
            '/ads/*$domain=~example.org',
            '||somesite.com/ads/',
            '/banner-$domain=~example.org',
            '||somesite.com^*/banner-$image',
            '!',
            '/banner/ads-',
            '/banner/*$domain=~example.org',
            '/banner2/ads-$image',
            '/banner2/*$domain=~example.org',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: ||somesite.com/ads/ already covered by /ads/* on line 1.'],
            [4, 'Redundant filter: ||somesite.com^*/banner-$image already covered by /banner- on line 3.'],
            [6, 'Redundant filter: /banner/ads- already covered by /banner/* on line 7.'],
            [8, 'Redundant filter: /banner2/ads-$image already covered by /banner2/* on line 9.'],
        ], self::RULE);
    }
}
