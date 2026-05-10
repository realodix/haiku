<?php

namespace Realodix\Haiku\Test\Linter\Rules\Preprocessor;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Test\TestCase;

class ExpressionCheckTest extends TestCase
{
    #[PHPUnit\Test]
    public function valid_directives(): void
    {
        $lines = [
            '!#if (adguard)',
            '!#endif',

            '!#if adguard',
            '!#endif',

            '!#if (adguard && !adguard_ext_safari)',
            '!#endif',

            '!#if adguard && !adguard_ext_safari',
            '!#endif',

            '!#if adguard || ext_ublock',
            '!#endif',

            '!#if ext_ubol',
            '!#endif',

            '!#if ext_abp',
            '!#endif',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function empty_condition(): void
    {
        $lines = [
            '!#if',
            '!#endif',

            '!#if ',
            '!#endif',

            '!#if ( )',
            '!#endif',
        ];

        $this->analyse($lines, [
            [1, 'The "!#if" statement must have a condition.'],
            [3, 'The "!#if" statement must have a condition.'],
            [5, 'The "!#if" statement must have a condition.'],
        ]);
    }

    #[PHPUnit\Test]
    public function unknown_value(): void
    {
        $lines = [
            '!#if unknown_value',
            '!#endif',

            '!#if adguard && unknown',
            '!#endif',

            '!#if (adguard || ext_ublock) && something_else',
            '!#endif',
        ];

        $this->analyse($lines, [
            [1, 'Unknown value "unknown_value" in "!#if" condition.'],
            [3, 'Unknown value "unknown" in "!#if" condition.'],
            [5, 'Unknown value "something_else" in "!#if" condition.'],
        ]);
    }

    #[PHPUnit\Test]
    public function mutually_exclusive_values(): void
    {
        $lines = [
            '!#if adguard && ext_ublock',
            '!#endif',

            '!#if env_firefox && env_chromium',
            '!#endif',
        ];
        $this->analyse($lines, [
            [1, '"adguard" and "ext_ublock" will always evaluate to false.'],
            [3, '"env_firefox" and "env_chromium" will always evaluate to false.'],
        ]);

        $lines = [
            '!#if env_firefox',
            '!#if env_chromium',
            '...',
            '!#endif',
            '!#endif',
        ];
        $this->analyse($lines, [
            [2, '"env_chromium" will always evaluate to "false" with "env_firefox" from the parent "!#if" on line 1.'],
        ]);
    }

    #[PHPUnit\Test]
    public function nested_exclusive_with_else(): void
    {
        $lines = [
            '!#if env_firefox',
            'a...',
            '!#else',
            '!#if env_chromium',
            'b...',
            '!#endif',
            'c...',
            '!#endif',
        ];
        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function invalid_else_condition(): void
    {
        $lines = [
            '!#if env_firefox',
            'rule_foo',
            '!#else ext_ublock',
            'rule_bar',
            '!#endif',
        ];

        $this->analyse($lines, [
            [3, 'The "!#else" statement must not have a condition.'],
        ]);
    }

    #[PHPUnit\Test]
    public function parenthesis_error(): void
    {
        $lines = [
            '!#if (',
            '!#endif',

            '!#if env_firefox)',
            '!#endif',

            '!#if (env_chromium',
            '!#endif',

            '!#if (unknown_value',
            '!#endif',
        ];
        $this->analyse($lines, [
            [1, 'Unclosed opening parenthesis.'],
            [3, 'Extra closing parenthesis without an opening one.'],
            [5, 'Unclosed opening parenthesis.'],
            [7, 'Unclosed opening parenthesis.'],
        ]);
    }
}
