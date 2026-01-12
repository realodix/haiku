<?php

namespace Realodix\Haiku\Test\Unit\Filter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class CosmeticTest extends TestCase
{
    // ========================================================================
    // General & File Structure Tests
    // ========================================================================

    #[PHPUnit\Test]
    public function blank_lines_are_removed(): void
    {
        $input = ['line1', '', 'line2', '   ', 'line3'];
        $expected = ['line1', 'line2', 'line3'];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function multiple_sections_are_processed_correctly(): void
    {
        $input = [
            '! Section 1: Network',
            '||example.com/ad',
            '||example.org/ad',
            '! Section 2: Element',
            'example.com##.ad',
            'example.org##.ad',
        ];
        $expected = [
            '! Section 1: Network',
            '||example.com/ad',
            '||example.org/ad',
            '! Section 2: Element',
            'example.com,example.org##.ad',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\DataProvider('removeExtraSpacesProvider')]
    #[PHPUnit\Test]
    public function removeExtraSpaces($actual, $expected): void
    {
        $this->assertSame([$expected], $this->fix([$actual]));
    }

    public static function removeExtraSpacesProvider(): array
    {
        return [
            // remove extra spaces
            [
                'example.com##.single-post  .code-block    +    .single-page:style(margin-top: 100px!important;)',
                'example.com##.single-post .code-block + .single-page:style(margin-top: 100px!important;)',
            ],
            [
                'example.com##header:style(margin:     0!important;     padding: 0!important;)',
                'example.com##header:style(margin: 0!important; padding: 0!important;)',
            ],

            // remove trailing spaces
            [
                'example.com## .ads',
                'example.com##.ads',
            ],
        ];
    }

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
        $input = ['c.com,b.com,~a.com##.ad'];
        $expected = ['~a.com,b.com,c.com##.ad'];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function domain_exclusion_is_handled(): void
    {
        $input = ['~b.com,a.com##.ad'];
        $expected = ['a.com,~b.com##.ad'];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function combines_rules_based_on_rules(): void
    {
        $input = [
            'a.com##.ad',
            'a.com,b.com##.ad',
            'a.com##.adRight',
            'a.com,b.com##.adRight',
            '!',
            'b.com,a.com##.ads',
            'a.com#?#.ads',
            'a.com#@#.ads',
            'c.com##.ads',
        ];
        $expected = [
            'a.com,b.com##.ad',
            'a.com,b.com##.adRight',
            '!',
            'a.com,b.com,c.com##.ads',
            'a.com#@#.ads',
            'a.com#?#.ads',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function combines_rules_based_on_domain_type(): void
    {
        $input = [
            // maybeMixed & maybeMixed
            'a.com,b.com##.ad',
            'c.com##.ad',
            '~d.com,e.com##.ad',
            '!', // negated & negated
            '~a.com,~b.com##.ad',
            '~c.com##.ad',
            '!', // maybeMixed & negated
            'x.com##.ad',
            '~y.com##.ad',
        ];
        $expected = [
            'a.com,b.com,c.com,~d.com,e.com##.ad',
            '!',
            '~a.com,~b.com,~c.com##.ad',
            '!',
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
    // Scriptlet Tests (`elementtidy`)
    // ========================================================================

    #[PHPUnit\Test]
    public function scriptlet_domains_are_sorted(): void
    {
        $input = ['c.com,b.com,a.com##+js(...)'];
        $expected = ['a.com,b.com,c.com##+js(...)'];
        $this->assertSame($expected, $this->fix($input));

        $input = ['c.com,b.com,a.com#%#//scriptlet(...)'];
        $expected = ['a.com,b.com,c.com#%#//scriptlet(...)'];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function scriptlet_domain_exclusion_is_handled(): void
    {
        $input = ['~b.com,a.com##+js(...)'];
        $expected = ['a.com,~b.com##+js(...)'];
        $this->assertSame($expected, $this->fix($input));

        $input = ['~b.com,a.com#%#//scriptlet(...)'];
        $expected = ['a.com,~b.com#%#//scriptlet(...)'];
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
            'a.com,~a.com,b.com##+js(...)',
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
            'a.com,~a.com,b.com#%#//scriptlet(...)',
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
