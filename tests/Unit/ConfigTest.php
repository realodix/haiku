<?php

namespace Realodix\Haiku\Test\Unit;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Config\Helper;
use Realodix\Haiku\Test\TestCase;

class ConfigTest extends TestCase
{
    #[PHPUnit\Test]
    public function mergeConfig(): void
    {
        $base = [
            'linter' => [
                'paths' => ['foo', 'bar', 'baz'],
                'rules' => [
                    'all' => true,
                ],
            ],
        ];
        $override = [
            'linter' => [
                'paths' => ['tests', 'src'],
                'rules' => [
                    'all' => false,
                ],
            ],
        ];
        $expected = [
            'linter' => [
                'paths' => ['foo', 'bar', 'baz', 'tests', 'src'],
                'rules' => [
                    'all' => false,
                ],
            ],
        ];
        $this->assertSame($expected, Helper::mergeConfig($base, $override));
    }
}
