<?php

namespace Realodix\Haiku\Test\Unit\Filter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class CosmeticTest extends TestCase
{
    // ========================================================================
    // Element Hiding Tests (`elementtidy`)
    // ========================================================================

    #[PHPUnit\Test]
    public function rules_order(): void
    {
        $input = [
            'example.com##.ads',
            'example.com#@#.ads',
            'example.com##ads',
            'example.com#@#ads',
            'example.com###ads',
            'example.com#@##ads',
            '/example\.com/###ads2',

            'example.com#?#.ads',
            'example.com#@?#.ads',

            'example.com#$#ads',
            'example.com#$?#ads',
            'example.com#@$#ads',
            'example.com#@$?#ads',

            'example.com##^ads',
            'example.com#@#^ads',
            'example.com$$ads',
            'example.com$@$ads',

            'example.com#%#ads',
            'example.com#@%#ads',

            'example.com##+js(...)',
            'example.com#@#+js(...)',
        ];
        $expected = [
            'example.com###ads',
            'example.com##.ads',
            'example.com##ads',
            'example.com#@##ads',
            'example.com#@#.ads',
            'example.com#@#ads',

            'example.com##^ads',
            'example.com#$#ads',
            'example.com#$?#ads',
            'example.com#?#.ads',

            'example.com#@#^ads',
            'example.com#@$#ads',
            'example.com#@$?#ads',
            'example.com#@?#.ads',
            'example.com$$ads',
            'example.com$@$ads',

            'example.com##+js(...)',
            'example.com#%#ads',
            'example.com#@#+js(...)',
            'example.com#@%#ads',

            '/example\.com/###ads2',
        ];

        arsort($input);
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function handle_regex_domains(): void
    {
        $input = [
            '/example\.com/###ads', // current is regex
            'example.com###ads',
            'example.com#@#ads', // next is regex
            '/example\.com/#@#ads',
            '/example\.com/###ads',
            '/example\.com/###ads',
        ];
        $expected = [
            'example.com###ads',
            'example.com#@#ads',
            '/example\.com/###ads',
            '/example\.com/#@#ads',
        ];
        $this->assertSame($expected, $this->fix($input));

        $v = ['/^https:\/\/[a-z\d]{4,}+\.[a-z\d]{12,}+\.(cfd|sbs|shop)$/##.ads'];
        $this->assertSame($v, $this->fix($v));
    }

    #[PHPUnit\Test]
    public function domains_are_sorted(): void
    {
        $input = ['~d.com,c.com,a.com,~b.com##.ad'];
        $expected = ['~b.com,~d.com,a.com,c.com##.ad'];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function combine_rules_based_on_rules(): void
    {
        $input = [
            'a.com##.ad',
            'b.com##.ad',
            'a.com##.adRight',
            'a.com,b.com##.adRight',
        ];
        $expected = [
            'a.com,b.com##.ad',
            'a.com,b.com##.adRight',
        ];
        $this->assertSame($expected, $this->fix($input));

        $input = [
            'b.com,a.com##.ads',
            'a.com#?#.ads',
            'a.com#@#.ads',
            'c.com##.ads',
        ];
        $expected = [
            'a.com,b.com,c.com##.ads',
            'a.com#@#.ads',
            'a.com#?#.ads',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function combine_not_compatible(): void
    {
        $input = [
            '##.ad',
            'a.com##.ad',
        ];

        $this->assertSame($input, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function combine_rules_based_on_domain_type(): void
    {
        // maybeMixed & maybeMixed
        $input = [
            'a.com,b.com##.ad',
            'c.com##.ad',
            '~d.com,e.com##.ad',
        ];
        $expected = [
            '~d.com,a.com,b.com,c.com,e.com##.ad',
        ];
        $this->assertSame($expected, $this->fix($input));

        // negated & negated
        $input = [
            '~a.com,~b.com##.ad',
            '~c.com##.ad',
        ];
        $expected = [
            '~a.com,~b.com,~c.com##.ad',
        ];
        $this->assertSame($expected, $this->fix($input));

        // maybeMixed & negated
        $input = [
            'x.com##.ad',
            '~y.com##.ad',
        ];
        $expected = [
            'x.com##.ad',
            '~y.com##.ad',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function element_rules_with_different_selectors_are_not_combined(): void
    {
        $input = [
            'a.com##.ad1',
            'b.com##.ad2',
        ];
        $this->assertSame($input, $this->fix($input));
    }

    // ========================================================================
    // Selector Normalization
    // ========================================================================

    #[PHPUnit\DataProvider('selector_removeleadingSpasesProvider')]
    #[PHPUnit\Test]
    public function selector_removeleadingSpases($actual, $expected): void
    {
        $this->assertSame([$expected], $this->fix([$actual]));
    }

    public static function selector_removeleadingSpasesProvider(): array
    {
        return [
            // remove trailing spaces
            [
                'example.com## .ads',
                'example.com##.ads',
            ],
            [
                'example.com##^ ads',
                'example.com##^ads',
            ],
            [
                'example.com$$ ads',
                'example.com$$ads',
            ],
            // this will be considered a comment
            [
                '## ads',
                '## ads',
            ],
            [
                '## .ads',
                '## .ads',
            ],
            [
                '## #ads',
                '## #ads',
            ],

            // In the future, if the removal of extra spaces is implemented,
            // this test should not fail.
            // remove extra spaces
            [
                'example.com##[class="ads   ads-header"]',
                'example.com##[class="ads   ads-header"]',
            ],
        ];
    }

    #[PHPUnit\Test]
    public function selector_convertExactAttributeSelector(): void
    {
        $flags = ['exact_attr_to_css_selector' => true];

        $input = [
            '##[id="adsId"] div[class="ads-Class"]',
            '##div[id="adsId"] + div[class="ads_Class"]',
            '##div[id="adsId"][class="adsClass"]',
            '!',
            'example.com##[id="adsId"] div[class="adsClass"]',
            'example.com##div[id="adsId"] + div[class="adsClass"]',
            'example.com##div[id="adsId"][class="adsClass"]',
        ];
        $expected = [
            '###adsId div.ads-Class',
            '##div#adsId + div.ads_Class',
            '##div#adsId.adsClass',
            '!',
            'example.com###adsId div.adsClass',
            'example.com##div#adsId + div.adsClass',
            'example.com##div#adsId.adsClass',
        ];
        $this->assertSame($expected, $this->fix($input, $flags));

        // partially converted
        $input = ['##div[id*="teaser"] div[class="adsClass"]'];
        $expected = ['##div[id*="teaser"] div.adsClass'];
        $this->assertSame($expected, $this->fix($input, $flags));

        // special case
        $input = [
            '##div[class="xl:max-w-[850px]"]',
            '!', '##div[class="aspect-3/2"]',
            '!', '##div[id="aspect-[calc(4*3+1)/3]"]',
            '!', '##div[id="will-change-[top,left]"]',
        ];
        $expected = [
            '##div.xl\:max-w-\[850px\]',
            '!', '##div.aspect-3\/2',
            '!', '##div#aspect-\[calc\(4\*3\+1\)\/3\]',
            '!', '##div#will-change-\[top\,left\]',
        ];
        $this->assertSame($expected, $this->fix($input, $flags));

        // not converted
        $input = [
            '##div[id="teaser 1"]',
            '##div[id="teaser" i]',
            '##div[id^="teaser"]',
        ];
        $this->assertSame($input, $this->fix($input, $flags));
    }

    // ========================================================================
    // Scriptlet Tests (`elementtidy`)
    // ========================================================================

    #[PHPUnit\Test]
    public function scriptlet_domains_are_sorted(): void
    {
        $input = ['~d.com,c.com,a.com,~b.com##+js(...)'];
        $expected = ['~b.com,~d.com,a.com,c.com##+js(...)'];
        $this->assertSame($expected, $this->fix($input));

        $input = ['~d.com,c.com,a.com,~b.com#%#//scriptlet(...)'];
        $expected = ['~b.com,~d.com,a.com,c.com#%#//scriptlet(...)'];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function scriptlet_rules_are_combined(): void
    {
        $input = [
            'a.com##+js(...)',
            'b.com##+js(...)',
            '!',
            'a.com##+js(...)',
            '~a.com,b.com##+js(...)',
        ];
        $expected = [
            'a.com,b.com##+js(...)',
            '!',
            '~a.com,a.com,b.com##+js(...)',
        ];
        $this->assertSame($expected, $this->fix($input));

        $input = [
            'a.com#%#//scriptlet(...)',
            'b.com#%#//scriptlet(...)',
            '!',
            'a.com#%#//scriptlet(...)',
            '~a.com,b.com#%#//scriptlet(...)',
        ];
        $expected = [
            'a.com,b.com#%#//scriptlet(...)',
            '!',
            '~a.com,a.com,b.com#%#//scriptlet(...)',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function scriptlet_rules_with_different_selectors_are_not_combined(): void
    {
        $input = [
            'a.com##+js(aopr, Notification)',
            'b.com##+js(aopw, Fingerprint2)',
        ];
        $this->assertSame($input, $this->fix($input));

        $input = [
            "example.org#%#//scriptlet('abort-on-property-read', 'alert')",
            "example.org#%#//scriptlet('remove-class', 'branding', 'div[class^=\"inner\"]')",
        ];
        $this->assertSame($input, $this->fix($input));
    }
}
