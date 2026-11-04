<?php

namespace Realodix\Haiku\Test\Unit\Support;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Support\Arr;
use Realodix\Haiku\Test\TestCase;

class ArrTest extends TestCase
{
    #[PHPUnit\Test]
    public function uniqueSortBy()
    {
        // '0' should not be removed by `array_filter()`
        $input = ['0', '0'];
        $expected = ['0'];
        $output = Arr::uniqueSortBy($input, fn($s) => $s);

        $this->assertSame($expected, $output);
    }

    #[PHPUnit\Test]
    public function renameKey()
    {
        $input = [
            'a' => 1,
            'b' => 2,
        ];
        $override = [
            'b' => 'foo',
        ];
        $expected = [
            'a' => 1,
            'foo' => 2,
        ];
        $this->assertSame($expected, Arr::renameKey($input, $override));

        $override = ['c' => 'foo'];
        $this->assertSame($input, Arr::renameKey($input, $override));

        $input = ['foo' => 'bar'];
        $this->assertSame($input, Arr::renameKey($input, $override));
    }

    #[PHPUnit\Test]
    public function renameKeyException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Keys "b" and "foo" cannot coexist.');

        $input = [
            'foo' => 1,
            'b' => 2,
        ];
        $override = [
            'b' => 'foo',
        ];
        Arr::renameKey($input, $override);
    }
}
