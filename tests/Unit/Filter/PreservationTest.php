<?php

namespace Realodix\Haiku\Test\Unit\Filter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class PreservationTest extends TestCase
{
    #[PHPUnit\Test]
    public function attribute_selector(): void
    {
        $input = [
            'example.com##.center[style^="min-height: 260px;"]:has(> [id^="div-Billboard_"])',
        ];
        $this->assertSame($input, $this->format($input));

        $input = [
            'example.com##div[alt~="Ad"]',
        ];
        $this->assertSame($input, $this->format($input));

        $input = [
            'example.com##img[alt*="banner" i]',
        ];
        $this->assertSame($input, $this->format($input));

        $input = [
            'example.com##[class$="-ad"]',
        ];
        $this->assertSame($input, $this->format($input));
    }

    #[PHPUnit\Test]
    public function css_combinator(): void
    {
        // + * should be preserved (adjacent sibling with universal selector)
        $input = [
            'example.com##.hghspd + *',
        ];
        $this->assertSame($input, $this->format($input));

        $input = [
            'example.com#?#.p__header > div.p__header-meta + div[class]:contains(/^\s$/)',
        ];
        $this->assertSame($input, $this->format($input));

        $input = [
            'example.com#%#!function(){const p={apply:(p,e,n)=>{const r=Reflect.apply(p,e,n),s=r?.[0]?.props?.data;return s&&null===s.user&&(r[0].props.data.user="guest"),r}};window.JSON.parse=new Proxy(window.JSON.parse,p)}();',
        ];
        $this->assertSame($input, $this->format($input));
    }

    #[PHPUnit\Test]
    public function pseudo_classes(): void
    {
        $input = [
            'example.com###Footer\:MainFooter\:SocialMediaFooter\:Text',
        ];
        $this->assertSame($input, $this->format($input));

        $input = [
            'example.com###Footer\:MainFooter\:SocialMediaFooter\:Text + ul',
        ];
        $this->assertSame($input, $this->format($input));
    }

    #[PHPUnit\Test]
    public function pseudo_classes_function(): void
    {
        $input = [
            'example.com##div > * > *:not(.comment-header)',
        ];
        $this->assertSame($input, $this->format($input));

        $input = [
            'example.com##div > *:has(.ad)',
        ];
        $this->assertSame($input, $this->format($input));

        $input = [
            'example.com##div[data-hb-id="Grid.Item"]:has(a[href*="&sponsoredid="])',
        ];
        $this->assertSame($input, $this->format($input));

        $input = [
            'example.com##*:matches-css(position: /fixed|absolute/):has(:is(a, canvas, image, form, [onclick], [href*="base64"]))',
        ];
        $this->assertSame($input, $this->format($input));
    }

    #[PHPUnit\Test]
    public function extended_syntax(): void
    {
        // has with attribute selectors
        $input = [
            'tripadvisor.com##div:has(> div[class="ui_columns is-multiline "])',
        ];
        $this->assertSame($input, $this->format($input));

        // abp extended selectors
        $input = [
            'kijiji.ca#?#[data-testid^="listing-card-list-item-"]:-abp-contains(TOP AD)',
        ];
        $this->assertSame($input, $this->format($input));

        // escaped brackets and colons in Tailwind-style classes
        $input = [
            'theepochtimes.com##.bg-\[\#f8f8f8\]',
        ];
        $this->assertSame($input, $this->format($input));
    }

    /**
     * Based on history, this rule was once misidentified as a network rule.
     */
    #[PHPUnit\Test]
    public function adguard_non_basic(): void
    {
        $input = [
            '[$app=~org.example.app1|~org.example.app2]example.com##.textad',
        ];
        $this->assertSame($input, $this->format($input));

        $input = [
            '[$path=/\/(maps|navi|web-maps)/]example.com,example.org#%#//scriptlet(...)',
        ];
        $this->assertSame($input, $this->format($input));

        $input = [
            '[$path=/images]example.com,example.org#%#//scriptlet(\'json-prune\', \'seatbid rtb direct\')',
        ];
        $this->assertSame($input, $this->format($input));
    }
}
