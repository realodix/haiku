<?php

namespace Realodix\Haiku\Test\Linter\Rules\Lines;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Linter\Rules\Lines\ExcessiveEmptyLinesCheck;
use Realodix\Haiku\Test\TestCase;

class ExcessiveEmptyLinesCheckTest extends TestCase
{
    private const RULE = [
        ExcessiveEmptyLinesCheck::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(LinterConfig::class)->rules = [
            'no_extra_blank_lines' => 2,
        ];
    }

    #[PHPUnit\Test]
    public function excessive_empty_lines(): void
    {
        $lines = [
            'foo',
            '',
            '',
            '',
            '', // 4 empty lines
            'bar',
            '',
            '', // 2 empty lines (ok)
        ];

        $this->analyse($lines, [
            [2, 'Too many consecutive empty lines (4), maximum allowed is 2.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function excessive_empty_lines_at_end(): void
    {
        $lines = [
            'foo',
            '',
            '',
            '',
            '',
            '', // 5 empty lines at end
        ];

        $this->analyse($lines, [
            [2, 'Too many consecutive empty lines (5), maximum allowed is 2.'],
        ], self::RULE);
    }
}
