<?php

namespace Realodix\Haiku\Test\Unit\Filter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Test\TestCase;

/**
 * https://adguard.com/kb/general/ad-filtering/create-own-filters/#non-basic-rules-modifiers
 */
class CosmeticAdgModifierTest extends TestCase
{
    #[PHPUnit\DataProvider('parseProvider')]
    #[PHPUnit\Test]
    public function parse(
        $rule,
        $expectedModifier,
        $expectedDomain,
        $expectedSeparator,
        $expectedRule,
    ) {
        preg_match(Regex::COSMETIC_RULE, $rule, $m);

        $this->assertSame($expectedModifier, $m[2], "Extracted modifier: $rule");
        $this->assertSame($expectedDomain, $m[3], "Extracted domain: $rule");
        $this->assertSame($expectedSeparator, $m[4], "Extracted separator: $rule");
        $this->assertSame($expectedRule, $m[5], "Extracted rule: $rule");
    }

    public static function parseProvider(): array
    {
        return [
            [
                'example.com,~example.org##.ads',
                '',
                'example.com,~example.org',
                '##',
                '.ads',
            ],

            [
                '[$app=org.example.app]example.com##.textad',
                '[$app=org.example.app]',
                'example.com',
                '##',
                '.textad',
            ],

            [
                '[$domain=example.com]##.textad',
                '[$domain=example.com]',
                '',
                '##',
                '.textad',
            ],
        ];
    }

    public function testBasic(): void
    {
        $input = [
            '[$app=org.example.app]example.com##.textad',
            '[$app=~org.example.app1|~org.example.app2]example.com##.textad',
            '[$path=/\/(maps|navi|web-maps)/]ya.ru,yandex.*#%#//scriptlet(...)',
        ];
        $this->assertSame($input, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function sortingRules(): void
    {
        $input = [
            'example.com##+js(nobab)',
            '##.top-banners',
            'example.com#?#div:has(> a[target="_blank"][rel="nofollow"])',
            '[$app=org.example.app]example.org,example.com##.textad',
        ];
        $expected = [
            '[$app=org.example.app]example.com,example.org##.textad',
            '##.top-banners',
            'example.com#?#div:has(> a[target="_blank"][rel="nofollow"])',
            'example.com##+js(nobab)',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function sortingDomain(): void
    {
        $input = [
            '[$app=org.example.app]example.org,example.com##.textad',
            '[$path=/page.html]example.org,example.com##.textad',
            '[$path=/page.html]example.org,example.com#%#//scriptlet(...)',
        ];
        $expected = [
            '[$app=org.example.app]example.com,example.org##.textad',
            '[$path=/page.html]example.com,example.org##.textad',
            '[$path=/page.html]example.com,example.org#%#//scriptlet(...)',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function adgModifier_sortingDomain(): void
    {
        $input = [
            '[$domain=b.com|a.com]##selector',
            '[$domain=b.com|a.com,app=test_app|com.apple.Safari]##selector',
        ];
        $expected = [
            '[$app=com.apple.Safari|test_app,domain=a.com|b.com]##selector',
            '[$domain=a.com|b.com]##selector',
        ];
        $this->assertSame($expected, $this->fix($input));

        // https://github.com/AdguardTeam/AdguardFilters/blob/280282dcf6/TurkishFilter/sections/antiadblock.txt#L159
        $input = [
            '[$domain=/(^\|.+\.)canlitribun\d+\.live/]#%#//scriptlet(\'prevent-xhr\', \'/advert.js\')',
        ];
        $this->assertSame($input, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function sortingModifier(): void
    {
        $input = [
            '[$domain=a.com,url=||example.com/content/*,app=com.apple.Safari,path=/page.html]##selector',
        ];

        $expected = [
            '[$app=com.apple.Safari,domain=a.com,path=/page.html,url=||example.com/content/*]##selector',
        ];

        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function combine(): void
    {
        $input = [
            '[$path=/page.html]##selector',
            '[$path=/page.html]##selector',
        ];
        $expected = [
            '[$path=/page.html]##selector',
        ];
        $this->assertSame($expected, $this->fix($input));

        $input = [
            '[$path=/page.html]example.com##selector',
            '[$path=/page.html]example.com##selector',
        ];
        $expected = [
            '[$path=/page.html]example.com##selector',
        ];
        $this->assertSame($expected, $this->fix($input));

        // not combined
        $input = [
            'example.com##selector',
            '[$path=/page.html]example.com##selector',
        ];
        $expected = [
            'example.com##selector',
            '[$path=/page.html]example.com##selector',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    /**
     * Because the syntax is quite complicated, the rules must be returned as is.
     */
    #[PHPUnit\Test]
    public function complicated(): void
    {
        $input = ['[$app=/[a-z]/]example.org,0.0.0.0##.ads'];
        $this->assertSame($input, $this->fix($input));

        $input = ['[$app=/^org\.example\.[ab].*/]example.com,~[::]##div[class="ads"]'];
        $this->assertSame($input, $this->fix($input));
    }
}
