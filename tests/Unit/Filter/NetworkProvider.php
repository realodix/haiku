<?php

namespace Realodix\Haiku\Test\Unit\Filter;

trait NetworkProvider
{
    public static function option_sort_order__has_domain_provider(): array
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

    public static function option_sort_order__has_value_provider(): array
    {
        return [
            // $csp
            [
                ['/ads.$domain=example.com,css,csp=script-src \'none\''],
                ['/ads.$css,csp=script-src \'none\',domain=example.com'],
            ],
            [
                ['@@/ads.$domain=example.com,css,csp'],
                ['@@/ads.$csp,css,domain=example.com'],
            ],

            // $header
            [
                ['/ads.$domain=example.com,xhr,header=via:/1\.1\s+google/'],
                ['/ads.$xhr,header=via:/1\.1\s+google/,domain=example.com'],
            ],

            // $method
            [
                ['/ads.$domain=example.com,xhr,method=~get'],
                ['/ads.$xhr,method=~get,domain=example.com'],
            ],

            // $permissions
            [
                ['|http*://*.*$doc,domain=~0.0.0.0|~127.0.0.1|~[::1]|~[::]|~local|~localhost,permissions=autoplay=(),xhr'],
                ['|http*://*.*$doc,xhr,permissions=autoplay=(),domain=~0.0.0.0|~127.0.0.1|~[::1]|~[::]|~local|~localhost'],
            ],

            // $redirect / $redirect-rule
            [
                ['$script,redirect=noopjs,domain=x.com,css'],
                ['$css,script,redirect=noopjs,domain=x.com'],
            ],
            [
                ['@@/ads.$domain=example.com,css,redirect'],
                ['@@/ads.$css,redirect,domain=example.com'],
            ],
            [
                ['$redirect-rule=noopjs,domain=x.com,css'],
                ['$css,redirect-rule=noopjs,domain=x.com'],
            ],
            [
                ['@@/ads.$domain=example.com,css,redirect-rule'],
                ['@@/ads.$css,redirect-rule,domain=example.com'],
            ],

            // $removeparam
            [
                ['$removeparam=/^(utm_source|utm_medium|utm_term)=/,domain=x.com,xhr'],
                ['$xhr,removeparam=/^(utm_source|utm_medium|utm_term)=/,domain=x.com'],
            ],
            [
                ['@@/ads.$~third-party,domain=x.com|y.com,removeparam,css'],
                ['@@/ads.$~third-party,css,removeparam,domain=x.com|y.com'],
            ],

            // $replace
            [
                ['/ads.$domain=example.com,css,replace=/X/Y/'],
                ['/ads.$css,replace=/X/Y/,domain=example.com'],
            ],
            [
                ['@@/ads.$domain=example.com,css,replace'],
                ['@@/ads.$css,replace,domain=example.com'],
            ],

            // $urlskip
            [
                ['/ads.$domain=example.com,xhr,urlskip=/\/dl\/(.+)/ -base64'],
                ['/ads.$xhr,urlskip=/\/dl\/(.+)/ -base64,domain=example.com'],
            ],

            // $uritransform
            [
                ['/ads.$domain=example.com,xhr,uritransform=/X/Y/'],
                ['/ads.$xhr,uritransform=/X/Y/,domain=example.com'],
            ],
            [
                ['@@/ads.$domain=example.com,xhr,uritransform'],
                ['@@/ads.$uritransform,xhr,domain=example.com'],
            ],

            // $urltransform
            [
                ['/ads.$domain=example.com,xhr,urltransform=/X/Y/'],
                ['/ads.$xhr,urltransform=/X/Y/,domain=example.com'],
            ],
            [
                ['@@/ads.$domain=example.com,xhr,urltransform'],
                ['@@/ads.$urltransform,xhr,domain=example.com'],
            ],

            // $reason
            [
                ['/ads.$reason="Site blocked: \"Known malware distributor\",xhr,domain=example.com'],
                ['/ads.$xhr,domain=example.com,reason="Site blocked: \"Known malware distributor\"'],
            ],
        ];
    }

    public static function lowercase_the_option_name_preserve_value_provider(): array
    {
        return [
            ['$Denyallow=/[A-Z-a-z-09]+/', '$denyallow=/[A-Z-a-z-09]+/'],
            ['$Domain=/[A-Z-a-z-09]+/', '$domain=/[A-Z-a-z-09]+/'],
            ['$From=/[A-Z-a-z-09]+/', '$from=/[A-Z-a-z-09]+/'],
            ['$Method=/[A-Z-a-z-09]+/', '$method=/[A-Z-a-z-09]+/'],
            ['$To=/[A-Z-a-z-09]+/', '$to=/[A-Z-a-z-09]+/'],

            ['||example.org^$Csp=Foo', '||example.org^$csp=Foo'],
            ['||example.org^$Reason=Foo', '||example.org^$reason=Foo'],
            ['||example.org^$Removeparam=Foo', '||example.org^$removeparam=Foo'],
            ['||example.org^$Replace=Foo', '||example.org^$replace=Foo'],
            ['||example.org^$Urlskip=Foo', '||example.org^$urlskip=Foo'],
            ['||example.org^$Uritransform=Foo', '||example.org^$uritransform=Foo'],
            ['||example.org^$Urltransform=Foo', '||example.org^$urltransform=Foo'],

            ['||example.org^$Cookie=Foo', '||example.org^$cookie=Foo'],
            ['||example.org^$Extension=Foo', '||example.org^$extension=Foo'],
            ['||example.org^$Hls=Foo', '||example.org^$hls=Foo'],
            ['||example.org^$Jsonprune=Foo', '||example.org^$jsonprune=Foo'],
            ['||example.org^$Xmlprune=Foo', '||example.org^$xmlprune=Foo'],

            ['||example.org^$Dnsrewrite=Foo', '||example.org^$dnsrewrite=Foo'],
            ['||example.org^$Dnstype=Foo', '||example.org^$dnstype=Foo'],
        ];
    }
}
