<?php

namespace Realodix\Haiku\Test\Unit\Filter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class NormalizationAndCleanupTest extends TestCase
{
    #[PHPUnit\Test]
    public function duplicateRules()
    {
        $input = [
            '-ads-',
            '-ads-',
        ];
        $expected = ['-ads-'];
        $this->assertSame($expected, $this->fix($input));

        $input = [
            '##.ads',
            '##.ads',
        ];
        $expected = ['##.ads'];
        $this->assertSame($expected, $this->fix($input));

        $input = [
            'example.com##.adsHeader',
            'example.com##.adsHeader',
        ];
        $expected = ['example.com##.adsHeader'];
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
    public function normalizeDomain()
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
        $this->assertSame($expected, $this->fix($input, ['xmode' => true]));

        $input = [
            '*$domain=api.example.com|example.*',
            'api.example.com,example.*##.ads',
        ];
        $this->assertSame($input, $this->fix($input, ['xmode' => true]));

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
        $this->assertSame($expected, $this->fix($input, ['xmode' => true]));

        // Just in case the user enters invalid input
        $input = ['192.*,192.168.1.1##.ads'];
        $this->assertSame($input, $this->fix($input, ['xmode' => true]));
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
        $this->assertSame($expected, $this->fix($input, ['xmode' => true]));

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
        $this->assertSame($expected, $this->fix($input, ['xmode' => true]));
    }
}
