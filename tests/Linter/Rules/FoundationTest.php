<?php

namespace Realodix\Haiku\Test\Linter\Rules;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class FoundationTest extends TestCase
{
    #[PHPUnit\Test]
    public function test_1(): void
    {
        $lines = [
            '*$_____',
            '/ads.$doc,to=example.com,reason="foo, bar"',
            '||cdn.edipresse.pl/player/wizaz/player.min.js$replace=/(appState\.status\.floating)=!0/\$1=!1/',
            '||giphy.com^$replace=/"htlAds\\":\[\\".{1\,5}\\".*?\]/"htlAds\\":\[\]/,document',
            '$script,domain=example.com,jsonprune=\$..[direct\,"rtbAuctionInfo"\, "blockId"\, "linkTail"\, "seatbid"]',
            '@@||apis.quantcast.mgr.consensu.org/CookieAccess$domain=blitz.gg,app=Blitz.exe',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function test_2(): void
    {
        $lines = [
            '##.ads',
            '$$advertisement-module',
            'example.com$$advertisement-module',
            '[$path=/app/consent]volksfreund.de#%#//scriptlet(\'trusted-click-element\', \'#consentAccept\', \'\', \'500\')',
            '[$path=/search]ya.ru,yandex.*##.AdvOffers',
            // is to much
            '*##._popIn_recommend_article_ad',
        ];

        $this->analyse($lines, [
            [3, 'Redundant filter: \'example.com$$advertisement-module\' already covered by \'$$advertisement-module\' on line 2.'],
        ]);
    }

    #[PHPUnit\Test]
    public function opt_replace(): void
    {
        $filePath = 'tests/Linter/_storage/foundation.txt';
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);

        $this->analyse($lines);
    }
}
