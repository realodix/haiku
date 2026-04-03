<?php

namespace Realodix\Haiku\Test\Linter\Rules\Lines;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\Lines\TooShortLineCheck;
use Realodix\Haiku\Test\TestCase;

class TooShortLineCheckTest extends TestCase
{
    #[PHPUnit\Test]
    public function too_short_line(): void
    {
        $lines = [
            'ab',    // Too short (2 < 3)
            'abc',   // OK (3 >= 3)
            '!a',    // Comment, OK
            '   a ', // Stripped to 'a', Too short
        ];

        $rules = [TooShortLineCheck::class];
        $this->analyse($lines, [
            [1, 'The line is too short (under 3 characters).'],
            [4, 'The line is too short (under 3 characters).'],
        ], $rules);
    }
}
