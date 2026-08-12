<?php

namespace Realodix\Haiku\Tests\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

final class CosmeticCanonicalSelectorTest extends TestCase
{
    #[PHPUnit\Test]
    public function exact_duplicate_with_reordered_classes(): void
    {
        // Detects duplicates when classes are in different orders (e.g., .a.b vs .b.a)
        $lines = [
            'example.com##.badge.active',
            'example.com##.active.badge',
            '##.foo.bar.baz',
            '##.baz.foo.bar',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: example.com##.active.badge already defined on line 1'],
            [4, 'Redundant filter: ##.baz.foo.bar already defined on line 3'],
        ]);
    }

    #[PHPUnit\Test]
    public function simple_class_subset_coverage(): void
    {
        // General selectors (.ad) cover more specific ones (.ad.banner)
        $lines = [
            'example.com##.ad',
            'example.com##.ad.banner',
            'example.com##.ad.banner.popup',
        ];

        $this->analyse($lines, [
            // [2, 'Redundant filter: example.com##.ad.banner is redundant due to more general selector on line 1.'],
            [3, 'Redundant filter: example.com##.ad.banner.popup is redundant due to more general selector on line 2.'],
        ]);
    }

    #[PHPUnit\Test]
    public function simple_class_subset_reverse_declaration_order(): void
    {
        // Ensures specific rules declared before general ones are still flagged as redundant
        $lines = [
            'example.com##.ad.banner.popup',
            'example.com##.ad.banner',
            'example.com##.ad',
        ];

        $this->analyse($lines, [
            [1, 'Redundant filter: example.com##.ad.banner.popup is redundant due to more general selector on line 2.'],
            // [1, 'Redundant filter: example.com##.ad.banner.popup is redundant due to more general selector on line 3.'],
            // [2, 'Redundant filter: example.com##.ad.banner is redundant due to more general selector on line 3.'],
        ]);
    }

    #[PHPUnit\Test]
    public function tag_and_id_coverage_rules(): void
    {
        $lines = [
            '##.ad',
            '##div.ad',                // Universal (.ad) covers tag-specific (div.ad)
            '##div.ad.banner',         // Universal (.ad) covers div.ad.banner
            '##span.ad',               // Universal (.ad) covers span.ad
            '##div#main',
            '##div#main.sidebar',      // div#main covers div#main.sidebar
            '##section#main.sidebar',  // Different tag than div#main, should not be covered
            '##div#other.sidebar',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: ##div.ad is redundant due to more general selector on line 1'],
            // [3, 'Redundant filter: ##div.ad.banner is redundant due to more general selector on line 1'],
            [4, 'Redundant filter: ##span.ad is redundant due to more general selector on line 1'],
            // [6, 'Redundant filter: ##div#main.sidebar is redundant due to more general selector on line 5'],
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
            // [2, 'Redundant filter: example.com##.ad.banner is redundant due to more general selector on line 1.'],
            // [3, 'Redundant filter: sub.example.com,test.com##.ad.promo is redundant due to more general selector on line 1.'],
            // [4, 'Redundant filter: example.org##.other.ad is redundant due to more general selector on line 1.'],
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
            // [2, 'Redundant filter: domain example.com in example.com##.ad.banner already covered on line 1.'],
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
            // [3, 'Redundant filter: #@#.ad.banner is redundant due to more general selector on line 2.'],
            // [4, 'Redundant filter: ##.ad.banner is redundant due to more general selector on line 1.'],
        ]);
    }

    #[PHPUnit\Test]
    public function unsupported_or_complex_selectors_fallback_to_exact_match(): void
    {
        // Complex selectors (combinators, pseudo-classes) cannot be parsed as simple selectors; fallback to string comparison
        $lines = [
            'example.com##div > .ad.banner',
            'example.com##div > .ad.banner',  // Exact match string -> duplicate
            'example.com##div > .banner.ad',  // Different order in complex selector -> not a duplicate
            'example.com##.ad:hover',
            'example.com##.ad:hover',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: example.com##div > .ad.banner already defined on line 1'],
            [5, 'Redundant filter: example.com##.ad:hover already defined on line 4'],
        ]);
    }
}
