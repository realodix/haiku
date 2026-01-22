<?php

namespace Realodix\Haiku\Test\Unit\Filter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Fixer\Classes\AdgModifierForElement;
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
            '[$app=test_app|com.apple.Safari,domain=b.com|a.com]##selector',
        ];

        $expected = [
            '[$app=com.apple.Safari|test_app,domain=a.com|b.com]##selector',
            '[$domain=a.com|b.com]##selector',
        ];

        $this->assertSame($expected, $this->fix($input));
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
        $this->assertSame(
            ['[$app=/[a-z]/]0.0.0.0,example.org##.ads'],
            $this->fix($input),
        );

        $input = ['[$app=/^org\.example\.[ab].*/]example.com,~[::]##div[class="ads"]'];
        $this->assertSame(
            ['[$app=/^org\.example\.[ab].*/]~[::],example.com##div[class="ads"]'],
            $this->fix($input),
        );
    }

    /**
     * This is an example provided in the documentation.
     *
     * https://adguard.com/kb/general/ad-filtering/create-own-filters/#non-basic-rules-modifiers
     */
    #[PHPUnit\DataProvider('extractProvider')]
    #[PHPUnit\Test]
    public function extract($actual, $expected): void
    {
        $this->assertSame(
            $expected,
            $this->callPrivateMethod(new AdgModifierForElement, 'extract', [$actual]),
        );
    }

    public static function extractProvider(): array
    {
        return [
            // $app
            [
                '[$app=org.example.app]example.com##.textad',
                '[$app=org.example.app]',
            ],
            [
                '[$app=~org.example.app1|~org.example.app2]example.com##.textad',
                '[$app=~org.example.app1|~org.example.app2]',
            ],
            [
                '[$app=com.apple.Safari]example.org#%#//scriptlet(\'prevent-setInterval\', \'check\', \'!300\')',
                '[$app=com.apple.Safari]',
            ],
            [
                '[$app=org.example.app]#@#.textad',
                '[$app=org.example.app]',
            ],

            // $domain
            [
                '[$domain=example.com]##.textad',
                '[$domain=example.com]',
            ],
            [
                '[$domain=example.com|example.org]###adblock',
                '[$domain=example.com|example.org]',
            ],
            [
                '[$domain=~example.com]##.textad',
                '[$domain=~example.com]',
            ],

            // $path
            [
                '[$path=page.html]##.textad',
                '[$path=page.html]',
            ],
            [
                '[$path=/page.html]##.textad',
                '[$path=/page.html]',
            ],
            [
                '[$path=|/page.html]##.textad',
                '[$path=|/page.html]',
            ],
            [
                '[$path=/page.html|]##.textad',
                '[$path=/page.html|]',
            ],
            [
                '[$path=/page*.html]example.com##.textad',
                '[$path=/page*.html]',
            ],
            [
                '[$path]example.com##.textad',
                '[$path]',
            ],
            [
                '[$domain=example.com,path=/page.html]##.textad',
                '[$domain=example.com,path=/page.html]',
            ],
            [
                '[$path=/\\/(sub1|sub2)\\/page\\.html/]##.textad',
                '[$path=/\\/(sub1|sub2)\\/page\\.html/]',
            ],

            // $url
            [
                '[$url=||example.com/content/*]##div.textad',
                '[$url=||example.com/content/*]',
            ],
            [
                '[$url=||example.org^]###adblock',
                '[$url=||example.org^]',
            ],
            [
                '[$url=/\[a-z\]+\\.example\\.com^/]##.textad',
                '[$url=/\[a-z\]+\\.example\\.com^/]',
            ],

            [ // https://github.com/AdguardTeam/tsurlfilter/blob/8a529d173b/packages/agtree/test/parser/cosmetic/adg-modifier-list.test.ts#L72
                '[$domain=/example[0-9]\.(com|org)/]##.ad',
                '[$domain=/example[0-9]\.(com|org)/]',
            ],
            [ // https://github.com/AdguardTeam/tsurlfilter/blob/8a529d173b/packages/agtree/test/parser/cosmetic/adg-modifier-list.test.ts#L124
                '[$domain=/example\d{1,}\.(com|org)/]##.ad',
                '[$domain=/example\d{1,}\.(com|org)/]',
            ],
            [ // https://github.com/AdguardTeam/tsurlfilter/blob/8a529d173b/packages/agtree/test/parser/cosmetic/adg-modifier-list.test.ts#L177
                '[$path=/id]/^[a-z0-9]{5,}\.(?=.*[a-z])(?=.*[0-9])[a-z0-9]{17,}\.(cfd|sbs|shop)$/##.ad',
                '[$path=/id]',
            ],
        ];
    }

    #[PHPUnit\DataProvider('extractComplicatedAdgModifierProvider')]
    #[PHPUnit\Test]
    public function extractComplicatedAdgModifier($actual, $expected): void
    {
        $this->assertSame(
            $expected,
            $this->callPrivateMethod(new AdgModifierForElement, 'extract', [$actual]),
        );
    }

    public static function extractComplicatedAdgModifierProvider(): array
    {
        return [
            [
                '[$app=/^org\.example\.[ab].*/]example.com,~[::]##.ads',
                '[$app=/^org\.example\.[ab].*/]',
            ],
            [
                '[$app=/^org\.example\.[ab].*/,path=/page.html]example.com,~[::]##.ads',
                '[$app=/^org\.example\.[ab].*/,path=/page.html]',
            ],
            [
                '[$path=/foo\]/bar/]##.ad',
                '[$path=/foo\]/bar/]',
            ],

            // Invalid bracket close
            [
                '[$app=/[a-z/]example.org,0.0.0.0##.ads',
                '[$app=/[a-z/]',
            ],
            [
                '[$app=/^org\.example\.[ab.*/]example.com,~[::]##.ads',
                '[$app=/^org\.example\.[ab.*/]',
            ],
            [
                '[$app=/^org\.example\.[ab.*/,domain=example.com,path=/page.html]example.com,~[::]##.ads',
                '[$app=/^org\.example\.[ab.*/,domain=example.com,path=/page.html]',
            ],
            [
                '[$domain=[::]##.ads',
                null,
            ],
        ];
    }
}
