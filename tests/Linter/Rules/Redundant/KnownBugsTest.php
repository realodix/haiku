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
    }

    #[PHPUnit\Test]
    public function net_filter_done(): void
    {
        $lines = [
            '||ads1-adnow.com',
            '||adnow.com',
            '||click-cdn.com',
            '||ck-cdn.com',

            '||alitems.com',
            '||alitems.co',
            '||example.co^',
            '||example.com^',
        ];
        $this->analyse($lines);
        $lines = [
            'ads1-adnow.com',
            'adnow.com',
            'click-cdn.com',
            'ck-cdn.com',
            'alitems.com',
            'alitems.co',
        ];
        $this->analyse($lines);
    }
}
