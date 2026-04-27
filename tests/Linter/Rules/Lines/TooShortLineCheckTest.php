<?php

namespace Realodix\Haiku\Test\Linter\Rules\Lines;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Test\TestCase;

class TooShortLineCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(LinterConfig::class)->rules = [
            'no_short_rules' => 4,
        ];
    }

    #[PHPUnit\Test]
    public function too_short_line(): void
    {
        $lines = [
            'bar',    // Too short (3 < 4)
            'foo$css,3p', // Stripped to 'foo', too short
            '   xyz ', // Stripped to 'xyz', too short

            'abcd',   // OK (4 >= 4)
            '!a',    // Comment, OK
        ];

        $this->analyse($lines, [
            [1, 'The rule is too short (under 4 characters).'],
            [2, 'The rule is too short (under 4 characters).'],
            [3, 'The rule is too short (under 4 characters).'],
        ]);
    }
}
