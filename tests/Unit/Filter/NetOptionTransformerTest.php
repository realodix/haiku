<?php

namespace Realodix\Haiku\Test\Unit\Filter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

final class NetOptionTransformerTest extends TestCase
{
    #[PHPUnit\Test]
    public function option_transforms(): void
    {
        $input = [
            '||example.com$_,removeparam=/^ss\\$/,__,image',
            '||example.com$domain=example.com,replace=/bad/good/,___,~third-party',
        ];
        $expected = [
            '||example.com$image,removeparam=/^ss\$/,__',
            '||example.com$~third-party,replace=/bad/good/,___,domain=example.com',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    #[PHPUnit\DataProvider('migrateDeprecatedOptionsProvider')]
    #[PHPUnit\Test]
    public function migrateDeprecatedOptions($input, $expected): void
    {
        $flags = ['xmode' => true];
        $this->assertSame($expected, $this->fix($input, $flags));
    }

    public static function migrateDeprecatedOptionsProvider(): array
    {
        return [
            [ // $empty
                ['||example.com/js/net.js$empty,script,domain=example.org'],
                ['||example.com/js/net.js$script,redirect=nooptext,domain=example.org'],
            ],
            [ // $mp4
                ['||example.com/video/*.mp4$mp4,domain=example.org'],
                ['||example.com/video/*.mp4$media,redirect=noopmp4-1s,domain=example.org'],
            ],
            [ // $object-subrequest
                ['$object-subrequest'],
                ['$object'],
            ],
            [ // $queryprune
                ['$xhr,queryprune', '$xhr,queryprune=utm_source'],
                ['$removeparam,xhr', '$xhr,removeparam=utm_source'],
            ],
        ];
    }
}
