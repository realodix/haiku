<?php

namespace Realodix\Haiku\Test\Unit\Filter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Fixer\Classes\NetworkOptionCombiner;
use Realodix\Haiku\Test\TestCase;

final class NetworkOptionCombinerTest extends TestCase
{
    private NetworkOptionCombiner $optionCombiner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->optionCombiner = new NetworkOptionCombiner;
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

            // negated options
            [['*$image,~css', '*$script']],

            // special options
            [['*$image,badfilter', '*$badfilter,css']],
            [['*$image,badfilter', '*$image']],
            [['*$image,important', '*$important']],
            [['*$image,all', '*$all']],
            [['*$image,other', '*$other']],
            [['*$image,1p', '*$1p']],
            [['*$image,3p', '*$3p']],
            [['*$image,first-party', '*$first-party']],
            [['*$image,third-party', '*$third-party']],
            [['*$image,strict1p', '*$strict1p']],
            [['*$image,strict3p', '*$strict3p']],
            [['*$image,strict3p', '*$strict3p']],
            [['*$image,strict-first-party', '*$strict-first-party']],
            [['*$image,strict-third-party', '*$strict-third-party']],

            // different case
            [
                [
                    '/ADS.$image,css',
                    '/ads.$image,css',
                ],
            ],
        ];
    }

    #[PHPUnit\DataProvider('aliasConflictProvider')]
    public function testAliasConflict($actual): void
    {
        $this->assertEqualsCanonicalizing($actual, $this->optionCombiner->applyFix($actual));
    }

    public static function aliasConflictProvider(): array
    {
        return [
            [['*$image,css', '*$image,stylesheet']],
            [['*$image,ehide', '*$image,elemhide']],
            [['*$image,frame', '*$image,subdocument']],
            [['*$image,generichide', '*$image,ghide']],
            [['*$image,specifichide', '*$image,shide']],
            [['*$image,xhr', '*$image,xmlhttprequest']],
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
