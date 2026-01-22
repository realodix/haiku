<?php

namespace Realodix\Haiku\Test\Unit\Filter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Fixer\Classes\NetOptionCombiner;
use Realodix\Haiku\Test\TestCase;

final class NetOptionCombinerTest extends TestCase
{
    private NetOptionCombiner $optionCombiner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->optionCombiner = new NetOptionCombiner;
    }

    public function testMergeSimpleOptions(): void
    {
        $actual = ['/ads.$image', '/ads.$css'];
        $expected = ['/ads.$image,css'];
        $this->assertSame($expected, $this->optionCombiner->applyFix($actual));

        $input = [
            '||example.com/banner/',
            '/ads.$image',
            '||example.org/banner/',
            '/ads.$css',
            '||example.com/banner/',
            '/ads.$frame',
        ];
        $expected = [
            '/ads.$image,css,frame',
            '||example.com/banner/',
            '||example.org/banner/',
        ];
        $this->assertSame($expected, $this->fix($input));
    }

    public function testMergeDuplicateOption(): void
    {
        $actual = ['/ads.$image,css', '/ads.$image'];
        $expected = ['/ads.$image,css'];
        $this->assertSame($expected, $this->optionCombiner->applyFix($actual));

        $actual = ['/ads.$image,stylesheet', '/ads.$stylesheet'];
        $expected = ['/ads.$image,stylesheet'];
        $this->assertSame($expected, $this->optionCombiner->applyFix($actual));

        $actual = ['/ads.$font,stylesheet,script', '/ads.$image,stylesheet,xhr'];
        $expected = ['/ads.$font,script,image,stylesheet,xhr'];
        $this->assertSame($expected, $this->optionCombiner->applyFix($actual));
    }

    public function testIgnoreOptionOrderDifferences(): void
    {
        $actual = ['/ads.$image,css', '/ads.$css,image'];
        $expected = ['/ads.$image,css'];
        $this->assertSame($expected, $this->optionCombiner->applyFix($actual));
    }

    #[PHPUnit\DataProvider('doNotMergeProvider')]
    public function testDoNotMerge($actual): void
    {
        $this->assertSame($actual, $this->optionCombiner->applyFix($actual));
    }

    public static function doNotMergeProvider(): array
    {
        return [
            // contains value
            [['/ads.$image,css,domain=a.com', '/ads.$image,domain=a.com']],

            // unregistered
            [['*$image,foo', '*$foo,css']],
            [['*$image,foo', '*$image']],
            [['*$foo', '*$image']],

            // different case
            [['/ADS.$image,css', '/ads.$image,css']],
        ];
    }

    #[PHPUnit\DataProvider('handlePolarityAcceptedProvider')]
    public function testHandlePolarity_Accepted($actual, $expected): void
    {
        $this->assertSame($expected, $this->optionCombiner->applyFix($actual));
    }

    public static function handlePolarityAcceptedProvider(): array
    {
        return [
            // -- negative + negative
            [
                ['*$~image', '*$~css'],
                ['*$~image,~css'],
            ],

            // -- mixed, need duplication
            [
                ['*$image,~css', '*$~css'],
                ['*$image,~css'],
            ],
            [
                ['*$~css', '*$image,~css'],
                ['*$~css,image'],
            ],
            [
                ['*$image,~css', '*$image'],
                ['*$image,~css'],
            ],
            [
                ['*$image', '*$image,~css'],
                ['*$image,~css'],
            ],
        ];
    }

    #[PHPUnit\DataProvider('handlePolarityDissallowedProvider')]
    public function testHandlePolarity_Dissallowed($actual, $expected): void
    {
        $this->assertSame($expected, $this->optionCombiner->applyFix($actual));
    }

    public static function handlePolarityDissallowedProvider(): array
    {
        return [
            // -- negative + positive
            [
                ['*$~css', '*$image'],
                ['*$image', '*$~css'],
            ],
            [
                ['*$image', '*$~css'],
                ['*$~css', '*$image'],
            ],

            // negative + mixed
            [
                ['*$~script', '*$image,~css'],
                ['*$image,~css', '*$~script'],
            ],
            [
                ['*$image,~css', '*$~script'],
                ['*$~script', '*$image,~css'],
            ],

            // positive + mixed
            [
                ['*$script', '*$image,~css'],
                ['*$image,~css', '*$script'],
            ],
            [
                ['*$image,~css', '*$script'],
                ['*$script', '*$image,~css'],
            ],

            // It looks like there is duplication, but there isn't
            [
                ['*$css', '*$image,~css'],
                ['*$image,~css', '*$css'],
            ],
            [
                ['*$image,~css', '*$css'],
                ['*$css', '*$image,~css'],
            ],
            [
                ['*$~css', '*$image,css'],
                ['*$image,css', '*$~css'],
            ],
            [
                ['*$image,css', '*$~css'],
                ['*$~css', '*$image,css'],
            ],

            // -- mixed + mixed
            [
                ['*$image,~css', '*$~css,script'],
                ['*$~css,script', '*$image,~css'],
            ],
        ];
    }

    #[PHPUnit\DataProvider('aliasConflictProvider')]
    public function testAliasConflict($actual, $expected): void
    {
        $this->assertEqualsCanonicalizing($expected, $this->optionCombiner->applyFix($actual));
    }

    public static function aliasConflictProvider(): array
    {
        return [
            [
                ['*$image,css', '*$image,stylesheet'],
                ['*$image,stylesheet'],
            ],
            [
                ['*$image,stylesheet', '*$image,css'],
                ['*$image,css'],
            ],
            [
                ['*$font,css,script,image', '*$image,stylesheet'],
                ['*$font,script,image,stylesheet'],
            ],

            [
                ['*$stylesheet', '*$css'],
                ['*$css'],
            ],
        ];
    }

    public function testPreserveSingleRule(): void
    {
        $rules = ['/ads.$script'];
        $this->assertSame(
            ['/ads.$script'],
            $this->optionCombiner->applyFix($rules),
        );
    }
}
