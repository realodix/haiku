<?php

namespace Realodix\Haiku\Test\Linter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Util;
use Realodix\Haiku\Test\TestCase;

class UtilsTest extends TestCase
{
    #[PHPUnit\Test]
    public function flatten(): void
    {
        $arr = [
            'a',
            'b' => ['alias' => ['b1'], 'abp' => ['b2']],
            'c' => ['abp' => ['c2']],
            'd' => ['d1'],
        ];
        $result = ['a', 'b', 'b1', 'b2', 'c', 'c2', 'd', 'd1'];

        $this->assertSame($result, Util::flatten($arr));
    }

    #[PHPUnit\Test]
    public function multiple_unknown_options(): void
    {
        $arr = [
            'a',
            'b' => ['alias' => ['b1'], 'abp' => ['b2']],
            'c' => ['abp' => ['c2']],
            'd' => ['d1'],
        ];
        $result = ['a', 'b', 'b1', 'b2', 'c', 'c2', 'd', 'd1'];

        $this->assertSame($result, Util::flattenWithFilter($arr));

        $arr = [
            'a',
            'b' => ['alias' => ['b1'], 'abp' => ['b2']],
            'c' => ['abp' => ['c2']],
            'd' => ['d1'],
        ];
        $result = ['a', 'b', 'b1', 'c', 'd', 'd1'];

        $this->assertSame($result, Util::flattenWithFilter($arr, ['alias']));
    }
}
