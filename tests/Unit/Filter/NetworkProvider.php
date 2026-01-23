<?php

namespace Realodix\Haiku\Test\Unit\Filter;

trait NetworkProvider
{
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
