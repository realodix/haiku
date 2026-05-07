<?php

namespace Realodix\Haiku\Test\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class GeneralTest extends TestCase
{
    private const RULE = [
        \Realodix\Haiku\Linter\Rules\Redundant\CosmeticCheck::class,
        \Realodix\Haiku\Linter\Rules\Redundant\NetworkCheck::class,
    ];

    #[PHPUnit\Test]
    public function redundant_1(): void
    {
        $lines = [
            '/ads$domain=~example.org', // L1: Almost Global (covers all except example.org)
            '/ads',                     // L2: Global (covers all)
            '/ads$domain=example.com',  // L3: Local (covers only example.com)

            '~example.com##.ads',   // L1: Almost Global (covers all except example.org)
            '##.ads',               // L2: Global (covers all)
            'example.org##.ads',    // L3: Local (covers only example.com)
        ];

        $this->analyse($lines, [
            [1, 'Redundant filter: /ads$domain=~example.org already covered by /ads on line 2.'],
            [3, 'Redundant filter: /ads$domain=example.com already covered by /ads on line 2.'],
            [4, 'Redundant filter: ~example.com##.ads already covered by ##.ads on line 5.'],
            [6, 'Redundant filter: example.org##.ads already covered by ##.ads on line 5.'],
        ]);
    }
}
