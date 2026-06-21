<?php

namespace Realodix\Haiku\Test\Unit\Support;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Support\Arr;
use Realodix\Haiku\Test\TestCase;

class ArrTest extends TestCase
{
    #[PHPUnit\Test]
    public function flattenWithKeys(): void
    {
        $arr = [
            'foo',
            'b' => ['alias' => ['b1'], 'abp' => ['foo']],
            'c' => ['abp' => ['c2']],
            'd' => ['d1'],
        ];
        $result = ['foo', 'b', 'b1', 'c', 'c2', 'd', 'd1'];
        $this->assertSame($result, Arr::flattenWithKeys($arr));

        $arr = [
            'a',
            'b' => ['alias' => ['b1'], 'abp' => ['b2']],
            'c' => ['abp' => ['c2']],
            'd' => ['d1'],
        ];
        $result = ['a', 'b', 'b1', 'c', 'd', 'd1'];
        $this->assertSame($result, Arr::flattenWithKeys($arr, ['alias']));
    }

    #[PHPUnit\Test]
    public function uniqueSortBy()
    {
        // '0' should not be removed by `array_filter()`
        $input = ['0', '0'];
        $expected = ['0'];
        $output = Arr::uniqueSortBy($input, fn($s) => $s);

        $this->assertSame($expected, $output);
    }
}
