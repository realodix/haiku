<?php

namespace Realodix\Haiku\Test\Unit\Filter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class NetworkTest extends TestCase
{
    #[PHPUnit\Test]
    public function rules_order(): void
    {
        $input = [
            '/ads.$domain=example.com',
            '||example.com^',
            '@@||example.com^',
        ];
        $expected = [
            '/ads.$domain=example.com',
            '||example.com^',
            '@@||example.com^',
        ];

        arsort($input);
        $this->assertSame($expected, $this->fix($input));
    }

    /**
     * @see \Realodix\Haiku\Fixer\Regex::NET_OPTION_DOMAIN
     */
    #[PHPUnit\DataProvider('combineSupportedOptionsProvider')]
    #[PHPUnit\Test]
    public function combine_supported_options(array $input, array $expected): void
    {
        $this->assertSame($expected, $this->fix($input));
    }

    public static function combineSupportedOptionsProvider(): array
    {
        return [
            [
                ['$domain=a.com', '$domain=b.com'],
                ['$domain=a.com|b.com'],
            ],
            [
                ['$from=a.com', '$from=b.com'],
                ['$from=a.com|b.com'],
            ],
            [
                ['$to=a.com', '$to=b.com'],
                ['$to=a.com|b.com'],
            ],
            [
                ['$denyallow=a.com', '$denyallow=b.com'],
                ['$denyallow=a.com|b.com'],
            ],
            [
                ['$denyallow=get', '$denyallow=post'],
                ['$denyallow=get|post'],
            ],

            // unsupported
            [
                ['$foo=a.com', '$foo=b.com'],
                ['$foo=a.com', '$foo=b.com'],
            ],
        ];
    }

    #[PHPUnit\Test]
    public function combines_rules_based_on_rules(): void
    {
        $input = [
            '-banner-$image,domain=a.com',
            '-banner-$image,domain=a.com|b.com',
            '-banner-$image,domain=a.com,css',

            '||example.com^$domain=a.com',
            '||example.com^$domain=b.com',
            '||example.com^$domain=c.com,css',
        ];
        $expected = [
            '-banner-$css,image,domain=a.com',
            '-banner-$image,domain=a.com|b.com',
            '||example.com^$css,domain=c.com',
            '||example.com^$domain=a.com|b.com',
        ];
        $this->assertSame($expected, $this->fix($input));

        $input = [
            '$permissions=storage-access=()\, camera=(),domain=b.com|a.com,image',
            '$domain=b.com|a.com,permissions=storage-access=()\, camera=(),image',
            '$permissions=storage-access=()\, camera=(),domain=b.com|a.com,image',
        ];
        $expected = [
            '$image,permissions=storage-access=()\, camera=(),domain=a.com|b.com',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function combines_rules_based_on_domain_type(): void
    {
        // maybeMixed & maybeMixed
        $input = [
            '||example.com^$domain=a.com|b.com',
            '||example.com^$domain=c.com',
            '||example.com^$domain=~d.com|e.com',
        ];
        $expected = [
            '||example.com^$domain=~d.com|a.com|b.com|c.com|e.com',
        ];
        $this->assertSame($expected, $this->fix($input));

        // negated & negated
        $input = [
            '||example.com^$domain=~a.com|~b.com',
            '||example.com^$domain=~c.com',
        ];
        $expected = [
            '||example.com^$domain=~a.com|~b.com|~c.com',
        ];
        $this->assertSame($expected, $this->fix($input));

        // maybeMixed & negated
        $input = [
            '||example.com^$domain=x.com',
            '||example.com^$domain=~y.com',
        ];
        $expected = [
            '||example.com^$domain=x.com',
            '||example.com^$domain=~y.com',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function option_sort__alphabetically_and_type(): void
    {
        $input = ['||example.com^$script,image,third-party,domain=a.com'];
        $expected = ['||example.com^$third-party,image,script,domain=a.com'];
        $this->assertSame($expected, $this->fix($input));

        $input = ['||example.com^$~image,image'];
        $this->assertSame($input, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function option_order(): void
    {
        // badfilter, important & match-case
        $input = ['*$important,domain=3p.com,css,badfilter,match-case'];
        $expected = ['*$badfilter,important,match-case,css,domain=3p.com'];
        $this->assertSame($expected, $this->fix($input));

        $input = ['*$css,~3p,third-party,strict3p,first-party,1p,strict1p,strict-first-party,strict-third-party'];
        $expected = ['*$strict-first-party,strict-third-party,strict1p,strict3p,1p,~3p,first-party,third-party,css'];
        $this->assertSame($expected, $this->fix($input));

        $input = [
            '$css,domain=x.com,reason="foo",redirect-rule=noopjs,script,',
        ];
        $expected = [
            '$css,script,redirect-rule=noopjs,domain=x.com,reason="foo"',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\DataProvider('optionOrdeDomainValueProvider')]
    #[PHPUnit\Test]
    public function option_orde__domain_value(array $input, array $expected): void
    {
        $this->assertSame($expected, $this->fix($input));
    }

    public static function optionOrdeDomainValueProvider(): array
    {
        return [
            // Domain
            [ // $denyallow
                ['||example.com^$3p,domain=a.com|b.com,denyallow=x.com|y.com,script'],
                ['||example.com^$3p,script,denyallow=x.com|y.com,domain=a.com|b.com'],
            ],
            [ // $domain
                ['||example.com^$script,domain=x.com,css'],
                ['||example.com^$css,script,domain=x.com'],
            ],
            [ // $to
                ['*$script,to=y.*|x.*,from=y.*|x.*,css'],
                ['*$css,script,from=x.*|y.*,to=x.*|y.*'],
            ],

            // $ipaddress
            // https://github.com/gorhill/uBlock/wiki/Static-filter-syntax#ipaddress
            [
                ['*$all,domain=~0.0.0.0|~127.0.0.1|~[::1]|~[::]|~local|~localhost,ipaddress=::,css'],
                ['*$all,css,domain=~0.0.0.0|~127.0.0.1|~[::1]|~[::]|~local|~localhost,ipaddress=::'],
            ],
        ];
    }

    #[PHPUnit\Test]
    public function lowercase_the_option_name(): void
    {
        $input = ['||example.com^$ALL'];
        $expected = ['||example.com^$all'];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\DataProvider('optDomainValuesAreSortedProvider')]
    #[PHPUnit\Test]
    public function optDomain_values_are_sorted(array $input, array $expected): void
    {
        $this->assertSame($expected, $this->fix($input));
    }

    public static function optDomainValuesAreSortedProvider(): array
    {
        return [
            [
                ['$domain=~d.com|c.com|a.com|~b.com'],
                ['$domain=~b.com|~d.com|a.com|c.com'],
            ],
            [
                ['$from=~d.com|c.com|a.com|~b.com,to=~d.com|c.com|a.com|~b.com'],
                ['$from=~b.com|~d.com|a.com|c.com,to=~b.com|~d.com|a.com|c.com'],
            ],
            [
                ['$denyallow=~d.com|c.com|a.com|~b.com'],
                ['$denyallow=~b.com|~d.com|a.com|c.com'],
            ],
            [
                ['$method=post|~get|delete'],
                ['$method=~get|delete|post'],
            ],
            [
                ['$ctag=device_pc|~device_phone'],
                ['$ctag=~device_phone|device_pc'],
            ],

            // case sensitive
            [
                ['$app=com.pelmorex.WeatherEyeAndroid|Kinoplay.exe'],
                ['$app=Kinoplay.exe|com.pelmorex.WeatherEyeAndroid'],
            ],
            [
                ['$dnstype=~CNAME|~A'],
                ['$dnstype=~A|~CNAME'],
            ],

            // The syntax is incorrect, but Haiku should not throw an error.
            [
                ['$domain'],
                ['$domain'],
            ],
        ];
    }

    #[PHPUnit\Test]
    public function optDomain_values_are_lowercase(): void
    {
        $input = [
            '$DENYALLOW=ExamPle.Com',
            '$DOMAIN=ExamPle.Com',
            '$FROM=ExamPle.Com',
            '$TO=ExamPle.Com',
            '!',
            '$METHOD=GET',
        ];

        $this->assertSame(array_map('strtolower', $input), $this->fix($input));
    }

    #[PHPUnit\DataProvider('lowercaseTheOptionNamePreserveValueProvider')]
    #[PHPUnit\Test]
    public function lowercase_the_option_name_preserve_value($input, $expected): void
    {
        $this->assertSame([$expected], $this->fix([$input]));
    }

    public static function lowercaseTheOptionNamePreserveValueProvider(): array
    {
        return [
            ['$Denyallow=/[A-Z-a-z-09]+/', '$denyallow=/[A-Z-a-z-09]+/'],
            ['$Domain=/[A-Z-a-z-09]+/', '$domain=/[A-Z-a-z-09]+/'],
            ['$From=/[A-Z-a-z-09]+/', '$from=/[A-Z-a-z-09]+/'],
            ['$Method=/[A-Z-a-z-09]+/', '$method=/[A-Z-a-z-09]+/'],
            ['$To=/[A-Z-a-z-09]+/', '$to=/[A-Z-a-z-09]+/'],

            ['||example.org^$Reason=Foo', '||example.org^$reason=Foo'],
            ['||example.org^$Removeparam=Foo', '||example.org^$removeparam=Foo'],
        ];
    }

    #[PHPUnit\Test]
    public function option_transforms(): void
    {
        // `$_`
        $input = [
            '||example.com$_,removeparam=/^ss\\$/,__,image',
            '||example.com$domain=example.com,replace=/bad/good/,___,~third-party',
        ];
        $expected = [
            '||example.com$image,removeparam=/^ss\$/,__',
            '||example.com$~third-party,replace=/bad/good/,___,domain=example.com',
        ];
        $this->assertSame($expected, $this->fix($input));

        // $empty
        $input = ['||example.com/js/net.js$script,empty,domain=example.org'];
        $expected = ['||example.com/js/net.js$script,redirect=nooptext,domain=example.org'];
        $this->assertSame($expected, $this->fix($input));

        // $mp4
        $input = ['||example.com/video/*.mp4$mp4,domain=example.org'];
        $expected = ['||example.com/video/*.mp4$media,redirect=noopmp4-1s,domain=example.org'];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\Test]
    public function handle_regex_domains(): void
    {
        // combine filter rules
        $input = [
            '/ads.$domain=/example\.com/', // current is regex
            '/ads.$domain=example.com',
            '@@/ads.$domain=example.com', // next is regex
            '@@/ads.$domain=/example\.com/',
            '/ads.$domain=/example\.com/',
            '/ads.$domain=/example\.com/',
        ];
        $expected = [
            '/ads.$domain=/example\.com/',
            '/ads.$domain=example.com',
            '@@/ads.$domain=/example\.com/',
            '@@/ads.$domain=example.com',
        ];
        $this->assertSame($expected, $this->fix($input));

        // left as is
        $str = ['/ads.$domain=/example\.(org|com)/'];
        $this->assertSame($str, $this->fix($str));
    }

    #[PHPUnit\DataProvider('handleRegexValuesProvider')]
    #[PHPUnit\Test]
    public function handle_regex_values($actual, $expected): void
    {
        $this->assertSame([$expected], $this->fix([$actual]));
    }

    public static function handleRegexValuesProvider(): array
    {
        return [
            // https://github.com/AdguardTeam/tsurlfilter/blob/8a529d173b/packages/agtree/test/parser/misc/modifier-list.test.ts#L743
            [
                '$replace=/(<VAST[\\s\\S]*?>)[\\s\\S]*<\\/VAST>/\\$1<\\/VAST>/i,path=/\\/(sub1|sub2)\\/page\\.html/',
                '$path=/\\/(sub1|sub2)\\/page\\.html/,replace=/(<VAST[\\s\\S]*?>)[\\s\\S]*<\\/VAST>/\\$1<\\/VAST>/i',
            ],
            [
                '$replace=/(<VAST[\\s\\S]*?>)[\\s\\S]*<\\/VAST>/\\$1<\\/VAST>/i,~path=/\\/(sub1|sub2)\\/page\\.html/',
                '$~path=/\\/(sub1|sub2)\\/page\\.html/,replace=/(<VAST[\\s\\S]*?>)[\\s\\S]*<\\/VAST>/\\$1<\\/VAST>/i',
            ],
        ];
    }
}
