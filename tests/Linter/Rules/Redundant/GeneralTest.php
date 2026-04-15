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

        $this->assertSame([
            'example.com,example.org,x.com,y.com##.ads',
        ],
            $this->fix($lines),
        );
    }

    #[PHPUnit\Test]
    public function redundant_2(): void
    {
        $lines = [
            '-banner-$image,domain=a.com|b.com',
            '-banner-$domain=a.com,image',        // Redundant
            '-banner-$image,domain=a.com,css',
        ];

        $this->analyse($lines, [
            [2, "Redundant filter: domain 'a.com' already covered on line 1."],
        ], self::RULE);

        $this->assertSame([
            '-banner-$css,image,domain=a.com',
            '-banner-$image,domain=a.com|b.com',
        ],
            $this->fix($lines),
        );
    }

    #[PHPUnit\Test]
    public function redundant_3(): void
    {
        $lines = [
            '*$domain=a.com|b.com',
            '*$from=a.com', // Redundant
            '*$denyallow=b.com',
            '*$to=b.com',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: domain \'a.com\' already covered on line 1.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function redundant_4(): void
    {
        $lines = [
            'example.com,example.org,example.com##.ads',
            '*$script,domain=example.com,from=example.com',
        ];

        $this->analyse($lines, [], self::RULE);
    }

    #[PHPUnit\Test]
    public function redundant_5(): void
    {
        $lines = [
            '*$domain=a.com|b.com,denyallow=~b.com,css',
            '*$domain=a.com,to=a.com|b.com,css',

            '*$to=a.com|b.com',
            '*$to=a.com',
        ];

        $this->analyse($lines, [
            [4, "Redundant filter: domain 'a.com' already covered on line 3."],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function redundant_6(): void
    {
        $lines = [
            '?url=http/$doc,to=com|io|net,match-case,urlskip=?url',
            '?URL=http/$doc,to=com|io|net,match-case,urlskip=?URL',
        ];

        $this->analyse($lines, [], self::RULE);
    }

    #[PHPUnit\Test]
    public function redundant_7(): void
    {
        $lines = [
            '||example.com^$image,script',
            '||example.com^$script,image',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: ||example.com^$script,image already defined on line 1.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function redundant_8(): void
    {
        $lines = [
            '||1xikk.world^',
            '||1xikk.world^$popup',
        ];

        $this->analyse($lines, [], self::RULE);
    }

    #[PHPUnit\Test]
    public function redundant_9(): void
    {
        $lines = [
            '##.ads',
            'example.com,example.org,example.site##.ads',
            '@@||example.org^$ghide',
            'x.com##.ads',
            '@@||x.com^$ghide',
            'y.com##.ads',
            'z.com##.ads',
            '@@*$ghide,domain=y.com|z.com',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: domain example.com already covered on line 1.'],
            [2, 'Redundant filter: domain example.site already covered on line 1.'],
        ], self::RULE);
    }
}
