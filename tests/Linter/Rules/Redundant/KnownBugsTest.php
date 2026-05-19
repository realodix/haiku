<?php

namespace Realodix\Haiku\Test\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class KnownBugsTest extends TestCase
{
   #[PHPUnit\Test]
    public function net_filter(): void
    {
        $lines = [
            '||example.com/path',
            '||example.com',

            '||example.org/path',
            '||example.org^',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: ||example.com/path already covered by ||example.com on line 2.'],
        ]);

        $lines = [
            '||ads1-adnow.com',
            '||adnow.com',
            '||click-cdn.com',
            '||ck-cdn.com',
            '||alitems.com',
            '||alitems.co',
        ];
        $this->analyse($lines, [
            [5, 'Redundant filter: ||alitems.com already covered by ||alitems.co on line 6.'],
        ]);
        $lines = [
            'ads1-adnow.com',
            'adnow.com',
            'click-cdn.com',
            'ck-cdn.com',
            'alitems.com',
            'alitems.co',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: ads1-adnow.com already covered by adnow.com on line 2.'],
            [3, 'Redundant filter: click-cdn.com already covered by ck-cdn.com on line 4.'],
            [5, 'Redundant filter: alitems.com already covered by alitems.co on line 6.'],
        ]);
    }
}
