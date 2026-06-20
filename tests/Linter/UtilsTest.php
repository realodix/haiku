<?php

namespace Realodix\Haiku\Test\Linter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Util;
use Realodix\Haiku\Test\TestCase;

class UtilsTest extends TestCase
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
        $this->assertSame($result, Util::flattenWithKeys($arr));

        $arr = [
            'a',
            'b' => ['alias' => ['b1'], 'abp' => ['b2']],
            'c' => ['abp' => ['c2']],
            'd' => ['d1'],
        ];
        $result = ['a', 'b', 'b1', 'c', 'd', 'd1'];
        $this->assertSame($result, Util::flattenWithKeys($arr, ['alias']));
    }
}
