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

    #[PHPUnit\DataProvider('optionNameMappingProvider')]
    #[PHPUnit\Test]
    public function convertOptionNameToLongerName($native, $alias): void
    {
        $this->assertSame([$native], $this->fix([$alias], ['option_format' => 'long']));
    }

    #[PHPUnit\DataProvider('optionNameMappingProvider')]
    #[PHPUnit\Test]
    public function convertOptionNameToShorterName($native, $alias): void
    {
        $this->assertSame([$alias], $this->fix([$native], ['option_format' => 'short']));
    }

    public static function optionNameMappingProvider(): array
    {
        return [
            ['/ads.$domain=~example.com', '/ads.$from=~example.com'],

            ['$first-party', '$1p'],
            ['$third-party', '$3p'],
            ['$strict-first-party', '$strict1p'],
            ['$strict-third-party', '$strict3p'],
            ['$document', '$doc'],
            ['$elemhide', '$ehide'],
            ['$generichide', '$ghide'],
            ['$specifichide', '$shide'],
            ['$stylesheet', '$css'],
            ['$subdocument', '$frame'],
            ['$xmlhttprequest', '$xhr'],

            [
                '/ads.$image,stylesheet,domain=~example.com',
                '/ads.$css,image,from=~example.com',
            ],
        ];
    }

    #[PHPUnit\DataProvider('optionNameToNativeNameProvider')]
    #[PHPUnit\Test]
    public function convertOptionNameToNativeName($alias, $native): void
    {
        $this->assertSame([$native], $this->fix([$alias], ['option_format' => 'native']));
    }

    public static function optionNameToNativeNameProvider(): array
    {
        return [
            ['/ads.$from=~example.com', '/ads.$domain=~example.com'],
            ['$css', '$stylesheet'],
            ['$doc', '$document'],
            ['$ehide', '$elemhide'],
            ['$frame', '$subdocument'],
            ['$ghide', '$generichide'],
            ['$xhr', '$xmlhttprequest'],
        ];
    }

    #[PHPUnit\Test]
    public function convertOptionNameSpecialSemantics(): void
    {
        $input = ['*$1p', '!', '*$first-party'];
        $this->assertSame(['*$first-party', '!', '*$first-party'], $this->fix($input, ['option_format' => 'long']));
        $this->assertSame(['*$1p', '!', '*$1p'], $this->fix($input, ['option_format' => 'short']));
        $this->assertSame(['*$~third-party', '!', '*$~third-party'], $this->fix($input, ['option_format' => 'native']));

        $input = ['*$~1p', '!', '*$~first-party'];
        $this->assertSame(['*$~first-party', '!', '*$~first-party'], $this->fix($input, ['option_format' => 'long']));
        $this->assertSame(['*$~1p', '!', '*$~1p'], $this->fix($input, ['option_format' => 'short']));
        $this->assertSame(['*$third-party', '!', '*$third-party'], $this->fix($input, ['option_format' => 'native']));

        $input = ['*$3p', '!', '*$third-party'];
        $this->assertSame(['*$third-party', '!', '*$third-party'], $this->fix($input, ['option_format' => 'long']));
        $this->assertSame(['*$3p', '!', '*$3p'], $this->fix($input, ['option_format' => 'short']));
        $this->assertSame(['*$third-party', '!', '*$third-party'], $this->fix($input, ['option_format' => 'native']));

        $input = ['*$~3p', '!', '*$~third-party'];
        $this->assertSame(['*$~third-party', '!', '*$~third-party'], $this->fix($input, ['option_format' => 'long']));
        $this->assertSame(['*$~3p', '!', '*$1p'], $this->fix($input, ['option_format' => 'short']));
        $this->assertSame(['*$~third-party', '!', '*$~third-party'], $this->fix($input, ['option_format' => 'native']));
    }

    #[PHPUnit\DataProvider('migrateDeprecatedOptionsProvider')]
    #[PHPUnit\Test]
    public function migrateDeprecatedOptions($input, $expected): void
    {
        $this->assertSame($expected, $this->fix($input));
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
                ['$~object-subrequest'],
                ['$~object'],
            ],
            [ // $queryprune
                ['$xhr,queryprune', '$xhr,queryprune=utm_source'],
                ['$removeparam,xhr', '$xhr,removeparam=utm_source'],
            ],
        ];
    }
}
