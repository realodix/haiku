<?php

namespace Realodix\Haiku\Test\Unit\Filter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

final class DomainNormalizerTest extends TestCase
{
    #[PHPUnit\Test]
    public function sort_domain(): void
    {
        $input = ['~d.com,c.com,a.com,~b.com##.ad'];
        $expected = ['~b.com,~d.com,a.com,c.com##.ad'];
        $this->assertSame($expected, $this->fix($input));

        $input = ['*$domain=example.com|~localhost|~127.0.0.1|0.0.0.0|~example.org'];
        $expected = ['*$domain=0.0.0.0|~127.0.0.1|example.com|~example.org|~localhost'];
        $this->assertSame($expected, $this->fix($input, ['domain_order' => 'name']));
        $expected = ['*$domain=~127.0.0.1|~example.org|~localhost|0.0.0.0|example.com'];
        $this->assertSame($expected, $this->fix($input, ['domain_order' => 'negated_first']));

        // TLD
        $input = ['info,me,pm,site,~edu.me,~edu.pm,~proton.me,example.com,~foo.example.com##.ad'];
        $expected = ['~edu.me,~edu.pm,~foo.example.com,~proton.me,example.com,info,me,pm,site##.ad'];
        $this->assertSame($expected, $this->fix($input));
        $input = ['*$domain=example.com|~localhost|~127.0.0.1|0.0.0.0|~example.org|uk'];
        $expected = ['*$domain=~127.0.0.1|~example.org|~localhost|0.0.0.0|example.com|uk'];
        $this->assertSame($expected, $this->fix($input));
        $input = ['*$script,3p,to=cloudfront.net,from=info|me|pm|site|~edu.pm|~gov.me|~proton.me|example.com|~foo.example.com'];
        $expected = ['*$3p,script,from=~edu.pm|~foo.example.com|~gov.me|~proton.me|example.com|info|me|pm|site,to=cloudfront.net'];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function duplicateDomains()
    {
        $input = [
            '*$domain=example.com|example.com',
            'example.com,example.com##.ads',
        ];
        $expected = [
            '*$domain=example.com',
            'example.com##.ads',
        ];
        $this->assertSame($expected, $this->fix($input));

        $input = [
            '*$domain=example.com|example.org',
            '*$domain=example.com',
            'example.com,example.org##.ads',
            'example.com##.ads',
        ];
        $expected = [
            '*$domain=example.com|example.org',
            'example.com,example.org##.ads',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function lowercase()
    {
        $input = [
            '*$domain=A.com|B.com',
            'A.com,B.com##.ads',
        ];
        $expected = [
            '*$domain=a.com|b.com',
            'a.com,b.com##.ads',
        ];
        $this->assertSame($expected, $this->fix($input));

        // regex domain will not affected
        $input = [
            '*$domain=/example\.[a-Z]/',
            '/example\.[a-Z]/##.ads',
        ];
        $this->assertSame($input, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function dirty_domain(): void
    {
        $input = [
            '-ads.$domain=example.com',
            '-ads.$domain=.Example.com/',
            '-ads.$domain=Example.com/',
            '/Example.com##.ads',
            '.Example.com/##.ads',
        ];
        $expected = [
            '-ads.$domain=example.com',
            'example.com##.ads',
        ];
        $this->assertSame($expected, $this->fix($input));

        // complex
        $input = [
            '/ads.$domain=/Example.com|.Example.com/|example.com',
            '/example.com,.example.com/,example.com##.ads',
        ];
        $expected = [
            '/ads.$domain=example.com',
            'example.com##.ads',
        ];
        $this->assertSame($expected, $this->fix($input));

        // regex
        $input = [
            '/ads.$domain=/REGEX/',
            '/REGEX/##.ads',
        ];
        $this->assertSame($input, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function domain_space(): void
    {
        $input = [
            'a.com , b.com ##.ads',
            '||example.com^$domain= a.com | b.com',
        ];
        $expected = [
            '||example.com^$domain=a.com|b.com',
            'a.com,b.com##.ads',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function domain_separator(): void
    {
        $input = [
            ',a.com,,b.com,##.ads',
            '||example.com^$domain=|a.com| |b.com|',
        ];
        $expected = [
            '||example.com^$domain=a.com|b.com',
            'a.com,b.com##.ads',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\DataProvider('wrongSeparatorProvider')]
    #[PHPUnit\Test]
    public function wrong_separator($input, $expected): void
    {
        $this->assertSame($expected, $this->fix($input));
    }

    public static function wrongSeparatorProvider(): array
    {
        return [
            [
                ['a.com|b.com##.ads'],
                ['a.com,b.com##.ads'],
            ],
            [
                ['||example.com^$domain=a.com,b.com,css'],
                ['||example.com^$css,domain=a.com|b.com'],
            ],

            // ensure that it can still be combined
            [
                ['a.com|c.com##.ads', 'b.com##.ads'],
                ['a.com,b.com,c.com##.ads'],
            ],
            [
                ['||example.com^$domain=a.com,c.com,css', '||example.com^$domain=b.com,css'],
                ['||example.com^$css,domain=a.com|b.com|c.com'],
            ],

            // contains regex, will be skipped
            [
                [
                    '$domain=a.com,c.com|/[a-z]{,3}/,css',
                    'a.com|b.com,/(com|org)/##.ads',
                ],
                [
                    '$css,domain=a.com,c.com|/[a-z]{,3}/',
                    'a.com|b.com,/(com|org)/##.ads',
                ],
            ],
        ];
    }

    #[PHPUnit\Test]
    public function wildcardDomainCoverage()
    {
        $input = [
            '*$domain=example.com|~example.net|example.*',
            'example.com,~example.net,example.*##.ads',
        ];
        $expected = [
            '*$domain=~example.net|example.*',
            '~example.net,example.*##.ads',
        ];
        $this->assertSame($expected, $this->fix($input));

        $input = [
            '*$domain=api.example.com|example.*',
            'api.example.com,example.*##.ads',
        ];
        $this->assertSame($input, $this->fix($input));

        $input = [
            '*$domain=example.com|~example.net',
            '*$domain=example.*',
            'example.com,example.*##.ads',
            '~example.net##.ads',
        ];
        $expected = [
            '*$domain=~example.net|example.*',
            'example.*##.ads',
            '~example.net##.ads',
        ];
        $this->assertSame($expected, $this->fix($input));

        // The parent domain is the negated domain.
        $input = ['*$domain=example.com|~example.net|~example.*'];
        $expected = ['*$domain=~example.*|~example.net|example.com'];
        $this->assertSame($expected, $this->fix($input));

        // Just in case the user enters invalid input
        $input = ['192.*,192.168.1.1##.ads'];
        $this->assertSame($input, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function subdomainCoverage()
    {
        $input = [
            '*$domain=~ads.example.co.uk|login.api.example.co.uk|api.example.co.uk|example.co.uk|login.example.co.uk',
            'example.com,~ads.example.com,api.example.com,example.org##.ads',
        ];
        $expected = [
            '*$domain=~ads.example.co.uk|example.co.uk',
            '~ads.example.com,example.com,example.org##.ads',
        ];
        $this->assertSame($expected, $this->fix($input));

        $input = [
            '*$domain=~ads.example.co.uk|login.api.example.co.uk|api.example.co.uk',
            '*$domain=example.org|example.co.uk',
            'example.com,api.example.com,example.org##.ads',
            '~ads.example.com##.ads',
        ];
        $expected = [
            '*$domain=~ads.example.co.uk|example.co.uk|example.org',
            'example.com,example.org##.ads',
            '~ads.example.com##.ads',
        ];
        $this->assertSame($expected, $this->fix($input));

        // The parent domain is the negated domain.
        $input = ['*$domain=~ads.example.co.uk|api.example.co.uk|~example.co.uk'];
        $expected = ['*$domain=~ads.example.co.uk|~example.co.uk|api.example.co.uk'];
        $this->assertSame($expected, $this->fix($input));
    }
}
