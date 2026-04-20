<?php

namespace Realodix\Haiku\Test\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\Redundant\NetworkCheck;
use Realodix\Haiku\Test\TestCase;

class NetworkCheckTest extends TestCase
{
    private const RULE = [NetworkCheck::class];

    #[PHPUnit\Test]
    public function exact_duplicate(): void
    {
        $lines = [
            '||example.com^',
            '||example.com^',
            '||example.org^$script',
            '||example.org^$script',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: ||example.com^ already defined on line 1.'],
            [4, 'Redundant filter: ||example.org^$script already defined on line 3.'],
        ], self::RULE);

        // case insensitive
        $lines = [
            '/ads/*',
            '/Ads/*',
            '||example.org^$script',
            '||example.org^$SCRIPT',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: /Ads/* already defined on line 1.'],
            [4, 'Redundant filter: ||example.org^$SCRIPT already defined on line 3.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function different_options_order(): void
    {
        $lines = [
            '*$image,script',
            '*$script,image',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: *$script,image already defined on line 1.'],
        ]);
    }

    #[PHPUnit\Test]
    public function domainRedundancy(): void
    {
        $lines = [
            '*$to=a.com|b.com',
            '*$to=a.com',
        ];
        $this->analyse($lines, [
            [2, "Redundant filter: domain 'a.com' already covered on line 1."],
        ]);

        $lines = [
            '-banner-$image,domain=a.com|b.com',
            '-banner-$from=a.com,image',        // Redundant
            '-banner-$image,domain=a.com,css',
        ];
        $this->analyse($lines, [
            [2, "Redundant filter: domain 'a.com' already covered on line 1."],
        ]);
    }

    #[PHPUnit\Test]
    public function respectMatchCaseOption(): void
    {
        $lines = [
            '?url=http/$doc,to=com|io|net,match-case,urlskip=?url',
            '?URL=http/$doc,to=com|io|net,match-case,urlskip=?URL',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function respectPopupOption(): void
    {
        $lines = [
            '/ads/*$popup',
            '/ads/*',
            '||example.com^',
            '||example.com^$popup',
        ];

        $this->analyse($lines);
    }
}
