<?php

namespace Realodix\Haiku\Tests\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

final class CosmeticCanonicalSelectorTest extends TestCase
{
    #[PHPUnit\Test]
    public function exact_duplicate_with_reordered(): void
    {
        // Detects duplicates when classes are in different orders (e.g., .a.b vs .b.a)
        $lines = [
            'example.com###badge.active',
            'example.com##.active#badge',
            '##.foo.bar.baz',
            '##.baz.foo.bar',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: example.com##.active#badge already defined on line 1'],
            [4, 'Redundant filter: ##.baz.foo.bar already defined on line 3'],
        ]);
    }

    #[PHPUnit\Test]
    public function simple_class_subset_coverage(): void
    {
        $lines = [
            'example.com##.ad.banner',
            'example.com##.ad',
            'example.com##div.ad.banner.popup',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: example.com##.ad.banner is redundant due to more general selector on line 2'],
            [3, 'Redundant filter: example.com##div.ad.banner.popup is redundant due to more general selector on line 2'],
        ]);

        $lines = [
            'example.com##.ad#banner',
            'example.com##.ad',
            'example.com##div.ad#banner.popup',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: example.com##.ad#banner is redundant due to more general selector on line 2'],
            [3, 'Redundant filter: example.com##div.ad#banner.popup is redundant due to more general selector on line 2'],
        ]);

        $lines = [
            'example.com##div.ad',
            'example.com##.ad',
            'example.com##div.ad.banner',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: example.com##div.ad is redundant due to more general selector on line 2'],
            [3, 'Redundant filter: example.com##div.ad.banner is redundant due to more general selector on line 2'],
        ]);

        $lines = [
            'example.com##div.Ad',
            'example.com##.ad',
            '##div.a.b',
            '##span.a',
        ];
        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function simple_class_subset_coverage_2(): void
    {
        $lines = [
            '##.a.b.c.d.e',
            '##.a.b.c.d.e.f.g.h.i.j.k',
        ];
        $this->analyse($lines, [
            [2, 'Redundant filter: ##.a.b.c.d.e.f.g.h.i.j.k is redundant due to more general selector on line 1'],
        ]);

        $lines = [
            '##.a.b.c.d.e',
            '##.a.c.e',
            '##.b.x',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: ##.a.b.c.d.e is redundant due to more general selector on line 2'],
        ]);
    }

    #[PHPUnit\Test]
    public function descendant_selectors_coverage(): void
    {
        $lines = [
            'example.com##.banner > .ad',
            'example.com##.ad',
            'example.com##.banner .ad',

            'example.com##.ad .banner',
            'example.com##.ad > .banner',
        ];
        $this->analyse($lines, [
            [1, 'Redundant filter: example.com##.banner > .ad is redundant due to more general selector on line 2'],
            [3, 'Redundant filter: example.com##.banner .ad is redundant due to more general selector on line 2'],
        ]);
    }

    #[PHPUnit\Test]
    public function escaped_characters_in_class_names(): void
    {
        // Validates regex handling for escaped characters commonly used in CSS (e.g., Tailwind classes)
        $lines = [
            '##.w-full.relative.z-0.flex.flex-1.flex-col.items-stretch.max-w-\[320px\].min-h-\[100px\]',
            '##.items-stretch.flex-col.flex-1.flex.z-0.relative.w-full',
            '##.lg\:min-h-\[132px\].flex.relative',
            '##.relative.lg\:min-h-\[132px\]',
            '##.foo\.bar.baz',
            '##.baz.foo\.bar',
        ];

        $this->analyse($lines, [
            [1, 'Redundant filter: ##.w-full.relative.z-0.flex.flex-1.flex-col.items-... is redundant due to more general selector on line 2'],
            [3, 'Redundant filter: ##.lg\:min-h-\[132px\].flex.relative is redundant due to more general selector on line 4'],
            [6, 'Redundant filter: ##.baz.foo\.bar already defined on line 5'],
        ]);
    }

    #[PHPUnit\Test]
    public function global_and_domain_level_redundancy_with_simple_selectors(): void
    {
        // Tests interaction between global rules and domain-specific rules using subset logic
        $lines = [
            '##.ad',
            'example.com##.ad.banner',
            'sub.example.com,test.com##.ad.promo',
            'example.org##.other.ad',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: example.com##.ad.banner is redundant due to more general selector on line 1'],
            [3, 'Redundant filter: sub.example.com,test.com##.ad.promo is redundant due to more general selector on line 1'],
            [4, 'Redundant filter: example.org##.other.ad is redundant due to more general selector on line 1'],
        ]);
    }

    #[PHPUnit\Test]
    public function domain_specific_partial_coverage(): void
    {
        // Checks if specific domain is covered while others in the same rule are not
        $lines = [
            'example.com##.ad',
            'example.com,unique.org##.ad.banner',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: domain example.com in example.com##.ad.banner already covered on line 1'],
        ]);
    }

    #[PHPUnit\Test]
    public function separator_mismatch_should_not_cover(): void
    {
        // Element hiding (##) should not cover element hiding exceptions (#@#)
        $lines = [
            '##.ad',
            '#@#.ad',
            '#@#.ad.banner',
            '##.ad.banner',
        ];

        $this->analyse($lines, [
            [3, 'Redundant filter: #@#.ad.banner is redundant due to more general selector on line 2'],
            [4, 'Redundant filter: ##.ad.banner is redundant due to more general selector on line 1'],
        ]);
    }

    #[PHPUnit\Test]
    public function unparsed_selector_fallback_to_exact_match(): void
    {
        $lines = [
            '##div > .ad.banner',             // Global rule (unparsed selector)
            'example.com##div > .ad.banner',  // Specific domain rule (identical selector)
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: example.com##div > .ad.banner already covered by ##div > .ad.banner on line 1'],
        ]);
    }

    #[PHPUnit\Test]
    public function unsupported_or_complex_selectors(): void
    {
        $lines = [
            'example.com##.ad',
            'example.com##.ad:has(.foo)',
            'example.com##.ad:hover',

            'example.com##.class > .ad', // this is bug, since it was unintentional
            'example.com###id > .ad',
            'example.com##div > .ad',
        ];
        $this->analyse($lines, [
            ['4', 'Redundant filter: example.com##.class > .ad is redundant due to more general selector on line 1'],
        ]);
    }
}
