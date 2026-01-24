<?php

namespace Realodix\Haiku\Test\Unit\Filter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class TypoTest extends TestCase
{
    use \Realodix\Haiku\Test\Unit\GeneralProvider;

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
            '||example.com^$domain=|a.com||b.com|',
        ];
        $expected = [
            '||example.com^$domain=a.com|b.com',
            'a.com,b.com##.ads',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\DataProvider('domainWrongSeparatorProvider')]
    #[PHPUnit\Test]
    public function domain_wrong_separator($actual, $expected): void
    {
        $this->assertSame($expected, $this->fix($actual));
    }

    public static function domainWrongSeparatorProvider(): array
    {
        return [
            [
                ['a.com|b.com##.ads'],
                ['a.com,b.com##.ads'],
            ],

            // ensure that it can still be combined
            [
                ['a.com|c.com##.ads', 'b.com##.ads'],
                ['a.com,b.com,c.com##.ads'],
            ],
        ];
    }

    #[PHPUnit\Test]
    public function network_option_separator(): void
    {
        $input = [
            '||example.com^$domain=a.com|b.com,,css,',
        ];
        $expected = [
            '||example.com^$css,domain=a.com|b.com',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function domain_value(): void
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
}
