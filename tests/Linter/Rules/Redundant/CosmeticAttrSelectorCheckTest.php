<?php

namespace Realodix\Haiku\Test\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\Redundant\CosmeticCheck;
use Realodix\Haiku\Test\TestCase;

class CosmeticAttrSelectorCheckTest extends TestCase
{
    private const RULE = [CosmeticCheck::class];

    #[PHPUnit\Test]
    public function test_no_error(): void
    {
        $lines = [
            '##img[href^="ab"]',
            '##img[href$="bc"]',
            'a.com#@#a[alt^="adv" i]',
            'b.com#@#a[alt*="adv" i]',
        ];
        $this->analyse($lines, [], self::RULE);
    }

    #[PHPUnit\Test]
    public function test_1(): void
    {
        $lines = [
            '##img[alt="advertising"]',
            '##img[alt^="adv"]',
            '##img[alt="Advertisement"]',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: \'##img[alt="advertising"]\' is redundant due to more general selector on line 2.'],
        ], self::RULE);

        $lines = [
            '##a[title="Ads-1" i]',
            '##a[title^="ADS" i]',
            '##a[title="ads-2"]',
            '!',
            '##img[alt="advertising"]',
            '##img[alt^="adv" i]',
            '##img[alt="Advertisement"]',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: \'##a[title="Ads-1" i]\' is redundant due to more general selector on line 2.'],
            [3, 'Redundant filter: \'##a[title="ads-2"]\' is redundant due to more general selector on line 2.'],
            [5, 'Redundant filter: \'##img[alt="advertising"]\' is redundant due to more general selector on line 6.'],
            [7, 'Redundant filter: \'##img[alt="Advertisement"]\' is redundant due to more general selector on line 6.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function test_1b(): void
    {
        $lines = [
            '##img[alt="advertising"]',
            '##img[alt^="adv"]',
            '##img[alt="Advertisement"]',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: \'##img[alt="advertising"]\' is redundant due to more general selector on line 2.'],
        ], self::RULE);

        $lines = [
            'example.com,example.org##a',
            'example.com##a',
            'a.com,x.com,b.com##a[title="Ads-1" i]',
            'a.com,b.com##a[title^="ADS" i]',

            'b.com,a.com##a[title^="ADv" i]',
            'a.com,x.com,b.com##a[title="Adv-1" i]',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: \'example.com##a\' already covered by \'##a\' on line 1.'],
            [3, 'Redundant filter: domain \'a.com\' in \'a.com##a[title="Ads-1" i]\' already covered on line 4.'],
            [3, 'Redundant filter: domain \'b.com\' in \'b.com##a[title="Ads-1" i]\' already covered on line 4.'],
            [6, 'Redundant filter: domain \'a.com\' in \'a.com##a[title="Adv-1" i]\' already covered on line 5.'],
            [6, 'Redundant filter: domain \'b.com\' in \'b.com##a[title="Adv-1" i]\' already covered on line 5.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function test_2(): void
    {
        $lines = [
            '##[href^="https://example.site"]',
            '##[href^="https://example."]',
            '##[href$="https://example.site"]',
            '##[href$=".site"]',
            '##[href*="https://x."]',
            '##[href*="https://x.com"]',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: \'##[href^="https://example.site"]\' is redundant due to more general selector on line 2.'],
            [3, 'Redundant filter: \'##[href$="https://example.site"]\' is redundant due to more general selector on line 4.'],
            [6, 'Redundant filter: \'##[href*="https://x.com"]\' is redundant due to more general selector on line 5.'],
        ], self::RULE);

        $lines = [
            '##[href*="https://example.com/"]',
            '##[href^="https://example.com/"]',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: \'##[href^="https://example.com/"]\' is redundant due to more general selector on line 1.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function test_3(): void
    {
        $lines = [
            'a.com,b.com##a[title="Ads-1"]',
            'a.com,b.com##a[title^="Ads"]',
            'a.com,b.com##a[title*="Ads"]',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: \'a.com,b.com##a[title="Ads-1"]\' is redundant due to more general selector on line 3.'],
            [2, 'Redundant filter: \'a.com,b.com##a[title^="Ads"]\' is redundant due to more general selector on line 3.'],
        ], self::RULE);

        $lines = [
            'a.com,b.com,c.com##a[title="Ads-1"]',
            'a.com,b.com,d.com##a[title^="Ads"]',
            'a.com,b.com##a[title*="Ads"]',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: domain \'a.com\' in \'a.com##a[title="Ads-1"]\' already covered on line 3.'],
            [1, 'Redundant filter: domain \'b.com\' in \'b.com##a[title="Ads-1"]\' already covered on line 3.'],
            [2, 'Redundant filter: domain \'a.com\' in \'a.com##a[title^="Ads"]\' already covered on line 3.'],
            [2, 'Redundant filter: domain \'b.com\' in \'b.com##a[title^="Ads"]\' already covered on line 3.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function test_4(): void
    {
        $lines = [
            '##img[alt^="ABC"]',
            '##img[alt^="abc" i]',

            '##img[alt^="foo" i]',
            '##img[alt^="FOO"]',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: \'##img[alt^="ABC"]\' is redundant due to more general selector on line 2.'],
            [4, 'Redundant filter: \'##img[alt^="FOO"]\' is redundant due to more general selector on line 3.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function test_5(): void
    {
        $lines = [
            '##img[alt^="FOO_BAR" i]',
            '##img[alt^="Foo_Bar_1" i]',
            '##img[alt^="Foo_Bar_2" i]',
            '##img[alt^="Foo_Bar_3" i]',
            '##img[alt^="foo_bar_2" i]',
            '##img[alt^="foo_bar" i]',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: \'##img[alt^="Foo_Bar_1" i]\' is redundant due to more general selector on line 1.'],
            [3, 'Redundant filter: \'##img[alt^="Foo_Bar_2" i]\' is redundant due to more general selector on line 1.'],
            [4, 'Redundant filter: \'##img[alt^="Foo_Bar_3" i]\' is redundant due to more general selector on line 1.'],
            [5, 'Redundant filter: \'##img[alt^="foo_bar_2" i]\' is redundant due to more general selector on line 1.'],
            [6, 'Redundant filter: \'##img[alt^="foo_bar" i]\' is redundant due to more general selector on line 1.'],
        ], self::RULE);

        $lines = [
            '##img[alt^="FOO_BAR" i]',
            '##img[alt^="Foo_Bar_1" i]',
            '##img[alt^="Foo_Bar_2" i]',
            '##img[alt^="Foo_Bar_3" i]',
            '##img[alt^="foo_bar_2" i]',
            '##img[alt*="foo_bar" i]',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: \'##img[alt^="FOO_BAR" i]\' is redundant due to more general selector on line 6.'],
            [2, 'Redundant filter: \'##img[alt^="Foo_Bar_1" i]\' is redundant due to more general selector on line 6.'],
            [3, 'Redundant filter: \'##img[alt^="Foo_Bar_2" i]\' is redundant due to more general selector on line 6.'],
            [4, 'Redundant filter: \'##img[alt^="Foo_Bar_3" i]\' is redundant due to more general selector on line 6.'],
            [5, 'Redundant filter: \'##img[alt^="foo_bar_2" i]\' is redundant due to more general selector on line 6.'],
        ], self::RULE);
    }
}
