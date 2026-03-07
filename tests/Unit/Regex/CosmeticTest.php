<?php

namespace Realodix\Haiku\Test\Unit\Regex;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Test\TestCase;
use Realodix\Haiku\Test\Unit\GeneralProvider;

class CosmeticTest extends TestCase
{
    use GeneralProvider;

    #[PHPUnit\DataProvider('isCosmeticRuleProvider')]
    #[PHPUnit\Test]
    public function cosmetic_rule($string)
    {
        $this->assertTrue((bool) preg_match(Regex::COSMETIC_RULE, $string));
    }

    #[PHPUnit\DataProvider('cosmeticRuleGeneralMatchProvider')]
    #[PHPUnit\Test]
    public function cosmeticRuleGeneral_match(
        $rule, $expectedMatch, $expectedSeparator, $expectedRule, $expectedDomain,
    ) {
        preg_match(Regex::COSMETIC_RULE, $rule, $m);

        $this->assertSame($expectedMatch, $m[0], "Full match: $rule");
        $this->assertSame($expectedSeparator, $m[4], "Extracted separator: $rule");
        $this->assertSame($expectedRule, $m[5], "Extracted rule: $rule");
        $this->assertSame($expectedDomain, $m[1], "Extracted domain: $rule");
    }

    public static function cosmeticRuleGeneralMatchProvider(): array
    {
        return [
            ['##div', '##div', '##', 'div', ''],
            ['#@#div', '#@#div', '#@#', 'div', ''],

            ['##.ads', '##.ads', '##', '.ads', ''],
            ['#@#.ads', '#@#.ads', '#@#', '.ads', ''],

            ['###ads', '###ads', '##', '#ads', ''],
            ['#@##ads', '#@##ads', '#@#', '#ads', ''],
        ];
    }

    #[PHPUnit\DataProvider('cosmeticRuleMatchProvider')]
    #[PHPUnit\Test]
    public function cosmetic_rule_match(
        $rule, $expectedMatch, $expectedDomain, $expectedSeparator, $expectedRule,
    ) {
        preg_match(Regex::COSMETIC_RULE, $rule, $m);

        $this->assertSame($expectedMatch, $m[0], "Full match: $rule");
        $this->assertSame($expectedDomain, $m[1], "Extracted domain: $rule");
        $this->assertSame($expectedSeparator, $m[4], "Extracted separator: $rule");
        $this->assertSame($expectedRule, $m[5], "Extracted rule: $rule");
    }

    public static function cosmeticRuleMatchProvider(): array
    {
        return [
            [
                'example.com,~auth.example.com##div',
                'example.com,~auth.example.com##div',
                'example.com,~auth.example.com',
                '##',
                'div',
            ],
            [
                'example.com,~auth.example.com#@#div',
                'example.com,~auth.example.com#@#div',
                'example.com,~auth.example.com',
                '#@#',
                'div',
            ],

            [
                'example.com,~auth.example.com##.ads',
                'example.com,~auth.example.com##.ads',
                'example.com,~auth.example.com',
                '##',
                '.ads',
            ],
            [
                '/^https?:\/\/[a-z]{8,15}\.com\/[%0-9a-zA-Z]{170,}$/##.ads',
                '/^https?:\/\/[a-z]{8,15}\.com\/[%0-9a-zA-Z]{170,}$/##.ads',
                '/^https?:\/\/[a-z]{8,15}\.com\/[%0-9a-zA-Z]{170,}$/',
                '##',
                '.ads',
            ],
            [
                '~/^https?:\/\/[a-z]{8,15}\.com\/[%0-9a-zA-Z]{170,}$/##.ads',
                '~/^https?:\/\/[a-z]{8,15}\.com\/[%0-9a-zA-Z]{170,}$/##.ads',
                '~/^https?:\/\/[a-z]{8,15}\.com\/[%0-9a-zA-Z]{170,}$/',
                '##',
                '.ads',
            ],
            [
                'example.com,~auth.example.com#@#.ads',
                'example.com,~auth.example.com#@#.ads',
                'example.com,~auth.example.com',
                '#@#',
                '.ads',
            ],

            [
                'example.com,~auth.example.com###ads',
                'example.com,~auth.example.com###ads',
                'example.com,~auth.example.com',
                '##',
                '#ads',
            ],
            [
                'example.com,~auth.example.com#@##ads',
                'example.com,~auth.example.com#@##ads',
                'example.com,~auth.example.com',
                '#@#',
                '#ads',
            ],

            [
                'facebook.com##:xpath(//div[@id="stream_pagelet"]//div[starts-with(@id,"hyperfeed_story_id_")][.//h6//span/text()="People You May Know"])',
                'facebook.com##:xpath(//div[@id="stream_pagelet"]//div[starts-with(@id,"hyperfeed_story_id_")][.//h6//span/text()="People You May Know"])',
                'facebook.com',
                '##',
                ':xpath(//div[@id="stream_pagelet"]//div[starts-with(@id,"hyperfeed_story_id_")][.//h6//span/text()="People You May Know"])',
            ],

            // Specific domain (regex)
            ['/regex/##div', '/regex/##div', '/regex/', '##', 'div'],

            // HTML filters
            [
                'example.com,~auth.example.com##^script:has-text(consentCookiePayload)',
                'example.com,~auth.example.com##^script:has-text(consentCookiePayload)',
                'example.com,~auth.example.com',
                '##^',
                'script:has-text(consentCookiePayload)',
            ],
            [
                'example.com#@#^.badstuff',
                'example.com#@#^.badstuff',
                'example.com',
                '#@#^',
                '.badstuff',
            ],

            // Response header filtering
            [
                'example.com,~auth.example.com##^responseheader(header-name)',
                'example.com,~auth.example.com##^responseheader(header-name)',
                'example.com,~auth.example.com',
                '##^',
                'responseheader(header-name)',
            ],

            // Scriptlet
            [
                'example.com,~auth.example.com##+js(...)',
                'example.com,~auth.example.com##+js(...)',
                'example.com,~auth.example.com',
                '##+',
                'js(...)',
            ],
            [
                'example.com,~auth.example.com#@#+js(...)',
                'example.com,~auth.example.com#@#+js(...)',
                'example.com,~auth.example.com',
                '#@#+',
                'js(...)',
            ],
        ];
    }

    #[PHPUnit\DataProvider('cosmeticRuleAdGuardMatchProvider')]
    #[PHPUnit\Test]
    public function cosmeticRuleAdGuardMatch(
        $rule, $expectedMatch, $expectedDomain, $expectedSeparator, $expectedRule,
    ) {
        $matched = preg_match(Regex::COSMETIC_RULE, $rule, $m);

        // There should be no matches at all
        if ($expectedMatch === null) {
            $this->assertSame(0, $matched);

            return;
        }

        $this->assertSame($expectedMatch, $m[0], "Full match: $rule");
        $this->assertSame($expectedDomain, $m[3], "Extracted domain: $rule");
        $this->assertSame($expectedSeparator, $m[4], "Extracted separator: $rule");
        $this->assertSame($expectedRule, $m[5], "Extracted rule: $rule");
    }

    public static function cosmeticRuleAdGuardMatchProvider(): array
    {
        return [
            [
                '[$app=~org.example.app1|~org.example.app2]example.com,~auth.example.com##.textad',
                '[$app=~org.example.app1|~org.example.app2]example.com,~auth.example.com##.textad',
                'example.com,~auth.example.com',
                '##',
                '.textad',
            ],

            [
                'example.com,~auth.example.com#$#body { background-color: #333!important; }',
                'example.com,~auth.example.com#$#body { background-color: #333!important; }',
                'example.com,~auth.example.com',
                '#$#',
                'body { background-color: #333!important; }',
            ],
            [
                'example.com,~auth.example.com#@$#.textad { visibility: hidden; }',
                'example.com,~auth.example.com#@$#.textad { visibility: hidden; }',
                'example.com,~auth.example.com',
                '#@$#',
                '.textad { visibility: hidden; }',
            ],

            [
                'example.com,~auth.example.com#?#div:has(> a[target="_blank"][rel="nofollow"])',
                'example.com,~auth.example.com#?#div:has(> a[target="_blank"][rel="nofollow"])',
                'example.com,~auth.example.com',
                '#?#',
                'div:has(> a[target="_blank"][rel="nofollow"])',
            ],
            [
                'example.com,~auth.example.com#@?#.banner:matches-css(width: 360px)',
                'example.com,~auth.example.com#@?#.banner:matches-css(width: 360px)',
                'example.com,~auth.example.com',
                '#@?#',
                '.banner:matches-css(width: 360px)',
            ],

            [
                'example.com,~auth.example.com#$?#h3:contains(cookies) { display: none!important; }',
                'example.com,~auth.example.com#$?#h3:contains(cookies) { display: none!important; }',
                'example.com,~auth.example.com',
                '#$?#',
                'h3:contains(cookies) { display: none!important; }',
            ],
            [
                'example.com,~auth.example.com#@$?#h3:contains(cookies) { display: none!important; }',
                'example.com,~auth.example.com#@$?#h3:contains(cookies) { display: none!important; }',
                'example.com,~auth.example.com',
                '#@$?#',
                'h3:contains(cookies) { display: none!important; }',
            ],

            [
                'example.com,~auth.example.com$$script[tag-content="alert(""this is ad"")"]',
                'example.com,~auth.example.com$$script[tag-content="alert(""this is ad"")"]',
                'example.com,~auth.example.com',
                '$$',
                'script[tag-content="alert(""this is ad"")"]',
            ],
            [
                'example.com,~auth.example.com$@$script[tag-content="banner"]',
                'example.com,~auth.example.com$@$script[tag-content="banner"]',
                'example.com,~auth.example.com',
                '$@$',
                'script[tag-content="banner"]',
            ],

            [
                'example.com,~auth.example.com#%#//scriptlet(...)',
                'example.com,~auth.example.com#%#//scriptlet(...)',
                'example.com,~auth.example.com',
                '#%#',
                '//scriptlet(...)',
            ],
            [
                'example.com,~auth.example.com#@%#//scriptlet(...)',
                'example.com,~auth.example.com#@%#//scriptlet(...)',
                'example.com,~auth.example.com',
                '#@%#',
                '//scriptlet(...)',
            ],

            // JavaScript rules, not included
            ['example.com,~auth.example.com#%#window.__gaq = undefined;', null, null, null, null],
            ['example.com,~auth.example.com#@%#window.__gaq = undefined;', null, null, null, null],
        ];
    }

    #[PHPUnit\DataProvider('cosmeticDomainProvider')]
    #[PHPUnit\Test]
    public function cosmetic_domain($rule, $expectedMatch, $expectedDomain)
    {
        $matched = preg_match(Regex::COSMETIC_DOMAIN, $rule, $m);

        // There should be no matches at all
        if ($expectedMatch === null) {
            $this->assertSame(0, $matched);

            return;
        }

        $this->assertSame($expectedMatch, $m[0], "Full match: $rule");
        $this->assertSame($expectedDomain, $m[1], "Extracted domain: $rule");
    }

    public static function cosmeticDomainProvider(): array
    {
        return [
            ['##div', '##', ''],
            ['example.com,~auth.example.com##div', 'example.com,~auth.example.com##', 'example.com,~auth.example.com'],
            ['example.com,~auth.example.com##.ads', 'example.com,~auth.example.com##', 'example.com,~auth.example.com'],

            ['example.com##+js(...)', 'example.com##', 'example.com'],
            ['example.com#%#//scriptlet(...)', 'example.com#%#', 'example.com'],

            // not included
            ['/regex/##div', null, null],
            ['[$app=~org.example.app1|~org.example.app2]example.com##.textad', null, null],
        ];
    }
}
