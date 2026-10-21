<?php

namespace Realodix\Haiku\Test\Unit\Support;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Config\Helper as ConfigHelper;
use Realodix\Haiku\Test\TestCase;

class HelperTest extends TestCase
{
    #[PHPUnit\Test]
    public function resolveOptions()
    {
        $base = [
            'a1' => true,
            'a2' => false,
            'a3' => null,
        ];

        $override = [
            'all' => true,
        ];
        $expected = [
            'a1' => true,
            'a2' => true,
            'a3' => null,
        ];
        $this->assertSame($expected, ConfigHelper::resolveOptions($base, $override));

        $override = [
            'all' => true,
            'a2' => 'foo',
        ];
        $expected = [
            'a1' => true,
            'a2' => 'foo',
            'a3' => null,
        ];
        $this->assertSame($expected, ConfigHelper::resolveOptions($base, $override));

        $override = [
            'all' => true,
            'a2' => 'foo',
        ];
        $enabledValues = [
            'a3' => 'foo',
        ];
        $expected = [
            'a1' => true,
            'a2' => 'foo',
            'a3' => 'foo',
        ];
        $this->assertSame($expected, ConfigHelper::resolveOptions($base, $override, $enabledValues));

        // Disable
        $override = [
            'all' => false,
            'a2' => 'foo',
        ];
        $enabledValues = [
            'a3' => 'foo',
        ];
        $expected = [
            'a1' => false,
            'a2' => 'foo',
            'a3' => null,
        ];
        $this->assertSame($expected, ConfigHelper::resolveOptions($base, $override, $enabledValues));
    }
}
