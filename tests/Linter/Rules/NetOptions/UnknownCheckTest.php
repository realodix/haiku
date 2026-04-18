<?php

namespace Realodix\Haiku\Test\Linter\Rules\NetOptions;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\NetOptions\UnknownCheck;
use Realodix\Haiku\Test\TestCase;

class UnknownCheckTest extends TestCase
{
    #[PHPUnit\Test]
    public function unknown_option(): void
    {
        $lines = [
            '||example.com^$script', // Known
            '||example.com^$foo',    // Unknown
        ];

        $this->analyse($lines, [
            [2, 'Unknown filter option: "foo".'],
        ]);
    }

    #[PHPUnit\Test]
    public function multiple_unknown_options(): void
    {
        $lines = [
            '||example.com^$foo,css,bar,baz',
        ];

        $this->analyse($lines, [
            [1, 'Unknown filter option: "foo".'],
            [1, 'Unknown filter option: "bar".'],
            [1, 'Unknown filter option: "baz".'],
        ]);
    }

    #[PHPUnit\Test]
    public function regex_with_comma_does_not_break_split(): void
    {
        $lines = [
            '||example.com^$foo=/regex[0-9]{1,2}/,css,bar',
        ];

        $this->analyse($lines, [
            [1, 'Unknown filter option: "foo".'],
            [1, 'Unknown filter option: "bar".'],
        ]);
    }

    #[PHPUnit\Test]
    public function splitOptions(): void
    {
        $input = 'css,"foo=\'a,b\',bar=1",script';
        $actual = $this->callPrivateMethod(app(UnknownCheck::class), 'splitOptions', [$input]);
        $expected = [
            'css',
            '"foo=\'a,b\',bar=1"',
            'script',
        ];

        $this->assertSame($expected, $actual);
    }
}
