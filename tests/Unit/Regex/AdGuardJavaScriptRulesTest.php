<?php

namespace Realodix\Haiku\Test\Unit\Regex;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Test\TestCase;

/**
 * https://adguard.com/kb/general/ad-filtering/create-own-filters/#javascript-rules
 */
class AdGuardJavaScriptRulesTest extends TestCase
{
    public static function regexMatchProvider(): array
    {
        return [
            [
                '#%#window.__gaq = undefined;',
                '#%#window.__gaq = undefined;',
                '',
                '#%#',
                'window.__gaq = undefined;',
            ],
            [
                '#@%#window.__gaq = undefined;',
                '#@%#window.__gaq = undefined;',
                '',
                '#@%#',
                'window.__gaq = undefined;',
            ],

            [
                'example.com,~auth.example.com#@%#window.__gaq = undefined;',
                'example.com,~auth.example.com#@%#window.__gaq = undefined;',
                'example.com,~auth.example.com',
                '#@%#',
                'window.__gaq = undefined;',
            ],
        ];
    }

    #[PHPUnit\DataProvider('regexDomainMatchProvider')]
    #[PHPUnit\Test]
    public function regex_domain_match($rule, $expectedMatch, $expectedDomain)
    {
        preg_match(Regex::COSMETIC_DOMAIN, $rule, $m);

        $this->assertSame($expectedMatch, $m[0], "Full match: $rule");
        $this->assertSame($expectedDomain, $m[1], "Extracted domain: $rule");
    }

    public static function regexDomainMatchProvider(): array
    {
        return [
            [
                'example.com#%#window.__gaq = undefined;',
                'example.com#%#',
                'example.com',
            ],

            [
                'example.com#@%#window.__gaq = undefined;',
                'example.com#@%#',
                'example.com',
            ],
        ];
    }

    #[PHPUnit\Test]
    public function sort_order(): void
    {
        $input = [
            'example.com##.ads',
            'example.com#%#window.__gaq = undefined;',
            "example.org#%#//scriptlet('abort-on-property-read', 'alert')",
            'example.com#@%#window.__gaq = undefined;',
            '###ads',
        ];
        $expected = [
            '###ads',
            'example.com##.ads',
            "example.org#%#//scriptlet('abort-on-property-read', 'alert')",
            'example.com#%#window.__gaq = undefined;',
            'example.com#@%#window.__gaq = undefined;',
        ];
        $this->assertSame($expected, $this->fix($input));
    }
}
