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
}
