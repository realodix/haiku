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
            'www.youtube.com',
            'youtube.com',
            'x.klarnacdn.net',
            'klarnacdn.net',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: www.youtube.com already covered by youtube.com on line 2.'],
            [3, 'Redundant filter: x.klarnacdn.net already covered by klarnacdn.net on line 4.'],
        ]);

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
            'keyvdowallet.me',
            't.me',
            'yandex.com',
            'ex.co',
            'ps.w.org',
            's.w.org',
        ];
        $this->analyse($lines);

        $this->analyse($lines);
        $lines = [
            'amazon.com.au',
            'amazon.com',
            'media-amazon.com',
            'm.media-amazon.com',
        ];
        $this->analyse($lines, [
            [4, 'Redundant filter: m.media-amazon.com already covered by media-amazon.com on line 3.'],
        ]);
    }
}
