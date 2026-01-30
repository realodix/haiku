<?php

namespace Realodix\Haiku\Test\Unit\Regex;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Fixer\Classes\NetworkTidy;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Test\TestCase;

class NetworkTest extends TestCase
{
    #[PHPUnit\DataProvider('isParseableNetworkRuleProvider')]
    #[PHPUnit\Test]
    public function isParseableNetworkRule($string, $expectedRule, $expectedOption)
    {
        preg_match(Regex::NET_OPTION, $string, $m);

        $this->assertSame($expectedRule, $m[1], "Rule match: $string");
        $this->assertSame($expectedOption, $m[2], "Option: $string");
    }

    public static function isParseableNetworkRuleProvider()
    {
        return [
            [
                '||pagead2.googlesyndication.com/pagead/js/adsbygoogle.js$script,xhr,redirect=googlesyndication_adsbygoogle.js:5,domain=~example.com',
                '||pagead2.googlesyndication.com/pagead/js/adsbygoogle.js',
                'script,xhr,redirect=googlesyndication_adsbygoogle.js:5,domain=~example.com',
            ],
            [
                '/^https?:\/\/www\.[0-9a-z]{8,}\.com\/[0-9a-z]{1,4}\.js$/$script,third-party,domain=example.org',
                '/^https?:\/\/www\.[0-9a-z]{8,}\.com\/[0-9a-z]{1,4}\.js$/',
                'script,third-party,domain=example.org',
            ],
            ['/ads.$domain=example.org', '/ads.', 'domain=example.org'],
            ['/banner/ads-$~xhr', '/banner/ads-', '~xhr'],
            ['/banner/ads-$css', '/banner/ads-', 'css'],
        ];
    }

    #[PHPUnit\DataProvider('isNotParseableNetworkRuleProvider')]
    #[PHPUnit\Test]
    public function isNotParseableNetworkRule($string)
    {
        $this->assertFalse((bool) preg_match(Regex::NET_OPTION, $string));
    }

    public static function isNotParseableNetworkRuleProvider()
    {
        return [
            ['/ads.'],
            ['/banner/ads-'],
            ['||pagead2.googlesyndication.com^'],
            ['||pagead2.googlesyndication.com/pagead/js/adsbygoogle.js'],
        ];
    }

    #[PHPUnit\DataProvider('not_network_option_provider')]
    #[PHPUnit\Test]
    public function not_network_option($string)
    {
        $this->assertFalse((bool) preg_match(Regex::NET_OPTION, $string));
    }

    public static function not_network_option_provider()
    {
        return [
            ['#$#.textad { visibility: hidden; }'],
            ['example.com#@$#.textad { visibility: hidden; }'],
            ['example.com#$#div.Ad-Container[id^="adblock-bait-element-"] { display: block !important; }'],
            ['example.com#$?#.banner { display: none; debug: true; }'],
            ['example.com#@$?#.banner { display: none; debug: true; }'],
        ];
    }

    #[PHPUnit\DataProvider('network_option_domain_provider')]
    #[PHPUnit\Test]
    public function network_option_domain($string, $expected)
    {
        preg_match(Regex::NET_OPTION_DOMAIN, $string, $matches);
        $this->assertSame($expected, $matches[1] ?? null);
    }

    public static function network_option_domain_provider()
    {
        return [
            ['$domain=~example.com', '~example.com'],
            ['$domain=example.com|~sub.example.com', 'example.com|~sub.example.com'],
            ['$script,domain=example.org', 'example.org'],

            ['$domain=~example.com', '~example.com'],
            [',domain=example.com|example.org', 'example.com|example.org'],
            ['$denyallow=example.net', 'example.net'],
            [',denyallow=example.net', 'example.net'],
            ['$from=example.net', 'example.net'],
            [',from=example.net', 'example.net'],
            ['$to=example.net', 'example.net'],
            [',to=example.net', 'example.net'],
            ['$method=post', 'post'],
            [',method=post', 'post'],

            ['$domain=example.com,script', null],
            ['$domain=', null],
            ['domain=example.com', null],
            ['$domains=example.com', null],
            ['||example.com^', null],
        ];
    }

    #[PHPUnit\DataProvider('network_option_split_provider')]
    #[PHPUnit\Test]
    public function network_option_split($string, $expected)
    {
        $this->assertSame(
            $expected,
            $this->callPrivateMethod(app(NetworkTidy::class), 'splitOptions', [$string]),
        );
    }

    public static function network_option_split_provider()
    {
        return [
            [
                '$~third-party,~xmlhttprequest,domain=~www.example.com',
                ['$~third-party', '~xmlhttprequest', 'domain=~www.example.com'],
            ],
            [
                '$_,removeparam=/^ss\\$/,__,image,1p,3p',
                ['$_', 'removeparam=/^ss\$/,__', 'image', '1p', '3p'],
            ],

            // only network options, then the filter rules will also be captured
            [
                '||example.com/*.js$1p,script',
                ['||example.com/*.js$1p', 'script'],
            ],

            // typo
            [ // uppercase network option
                '$IMAGE,DOMAIN=a.com|b.com',
                ['$IMAGE', 'DOMAIN=a.com|b.com'],
            ],
            [ // has superfluous commas
                '*$,script,,header=via:/1\.1\s+google/,,css,',
                ['*$', 'script', '', 'header=via:/1\.1\s+google/', '', 'css', ''],
            ],

            // ignore: escape comma
            [
                '$image,permissions=storage-access=()\, camera=(),domain=a.com|b.com',
                ['$image', 'permissions=storage-access=()\, camera=()', 'domain=a.com|b.com'],
            ],
            [
                '||example.org^$hls=/#UPLYNK-SEGMENT:.*\,ad/t,domain=/a\,b/',
                ['||example.org^$hls=/#UPLYNK-SEGMENT:.*\,ad/t', 'domain=/a\,b/'],
            ],

            // ignore: comma inside regex
            [
                '/ads.$domain=/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.(cfd|sbs|shop)$/',
                ['/ads.$domain=/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.(cfd|sbs|shop)$/'],
            ],
            [
                '/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.(cfd|sbs|shop)$/',
                ['/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.(cfd|sbs|shop)$/'],
            ],
            [ // https://github.com/uBlockOrigin/uBlock-issues/discussions/2234#discussioncomment-5403472
                '$all,~doc,domain=example.*|/example\.([a-z]{1,2}|[a-z]{4,16})/',
                ['$all', '~doc', 'domain=example.*|/example\.([a-z]{1,2}|[a-z]{4,16})/'],
            ],
        ];
    }
}
