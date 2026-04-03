<?php

namespace Realodix\Haiku\Test\Linter\Rules;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class DomainCheckTest extends TestCase
{
    #[PHPUnit\Test]
    public function empty_domain(): void
    {
        $lines = [
            '##.ads',  // OK
            'example.com,example.org##.ads',  // OK
            ',example.com##.ads',             // Empty start
            'example.com,,example.org##.ads', // Empty middle
            'example.com,##.ads',             // Trailing comma
            '||ex.com^$domain=|a.com',        // Empty network option
        ];

        $this->analyse($lines, [
            [3, 'Unexpected empty domain in cosmetic rule.'],
            [4, 'Unexpected empty domain in cosmetic rule.'],
            [5, 'Unexpected empty domain in cosmetic rule.'],
            [6, 'Unexpected empty domain in network filter.'],
        ]);
    }

    #[PHPUnit\Test]
    public function bad_domain(): void
    {
        $lines = [
            'a,example.com,c##.ads',
            '*$domain=a|example.com|c',
            'example.##.ad',
            'e xample.com##.ad',
        ];
        $this->analyse($lines, [
            [1, 'Bad domain name: "a"'],
            [1, 'Bad domain name: "c"'],
            [2, 'Bad domain name: "a"'],
            [2, 'Bad domain name: "c"'],
            [3, 'Bad domain name: "example."'],
            [4, 'Bad domain name: "e xample.com" contains unnecessary whitespace.'],
        ]);

        $lines = [
            '*$domain=example.',
            '*$domain=0.0.0.',
            '!',
            '*$domain=/domain\.com/',
            '*$domain=/domain.com',
            '*$domain=.domain.com',
            '*$domain=domain.com/',
        ];
        $this->analyse($lines, [
            [1, 'Bad domain name: "example."'],
            [5, 'Bad domain name: "/domain.com"'],
            [6, 'Bad domain name: ".domain.com"'],
            [7, 'Bad domain name: "domain.com/"'],
        ]);
    }

    #[PHPUnit\Test]
    public function bad_domain_contains_whitespaces(): void
    {
        $lines = [
            '/single$domain= example.com ',
            '/foo$domain=example.com | example.org',
            '/bar$domain=example.com| example.org',
            '/baz$domain=example.com |example.org',
            'example.com , example.org##foo',
            'example.com, example.org##bar',
            'example.com ,example.org##baz',
        ];

        $this->analyse($lines, [
            [1, 'Bad domain name: " example.com" contains unnecessary whitespace.'],
            [2, 'Bad domain name: "example.com " contains unnecessary whitespace.'],
            [2, 'Bad domain name: " example.org" contains unnecessary whitespace.'],
            [3, 'Bad domain name: " example.org" contains unnecessary whitespace.'],
            [4, 'Bad domain name: "example.com " contains unnecessary whitespace.'],
            [5, 'Bad domain name: "example.com " contains unnecessary whitespace.'],
            [5, 'Bad domain name: " example.org" contains unnecessary whitespace.'],
            [6, 'Bad domain name: " example.org" contains unnecessary whitespace.'],
            [7, 'Bad domain name: "example.com " contains unnecessary whitespace.'],
        ]);
    }

    #[PHPUnit\Test]
    public function lowercase_domain_only(): void
    {
        $lines = [
            'Example.com,example.org,X.COM##.ads',
            '*$domain=Example.com|example.org|~X.COM',
        ];

        $this->analyse($lines, [
            [1, 'Domain Example.com must be lowercase.'],
            [1, 'Domain X.COM must be lowercase.'],
            [2, 'Domain Example.com must be lowercase.'],
            [2, 'Domain ~X.COM must be lowercase.'],
        ]);
    }

    #[PHPUnit\Test]
    public function duplicate_domain(): void
    {
        $lines = [
            'example.com,example.org,example.com##.ads',
            '*$domain=example.com|example.org|example.com',
            '~example.com,~example.org,~example.com##.ads',
        ];

        $this->analyse($lines, [
            [1, 'Duplicate domain "example.com".'],
            [2, 'Duplicate domain "example.com".'],
            [3, 'Duplicate domain "~example.com".'],
        ]);
    }

    #[PHPUnit\Test]
    public function contradictory_domain(): void
    {
        $lines = [
            'example.com,example.org,~example.com##.ads',
            '~example.com,example.org,example.com##.ads',
            '*$domain=example.com|example.org|~example.com',
        ];

        $this->analyse($lines, [
            [1, 'Contradictory domain example.com detected.'],
            [2, 'Contradictory domain example.com detected.'],
            [3, 'Contradictory domain example.com detected.'],
        ]);
    }

    #[PHPUnit\Test]
    public function regex_domain_is_skipped(): void
    {
        // Currently we skip regex domains to avoid complex comma handling
        $lines = [
            '||example.com^$domain=/regex{1,3}/',
            '/a,b/##.ads',
        ];

        $this->analyse($lines, []);
    }
}
