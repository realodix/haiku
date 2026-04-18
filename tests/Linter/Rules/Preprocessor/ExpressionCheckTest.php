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
    public function unknown_tokens(): void
    {
        $lines = [
            '!#if unknown_token',
            '!#endif',

            '!#if adguard && unknown',
            '!#endif',

            '!#if (adguard || ext_ublock) && something_else',
            '!#endif',
        ];

        $this->analyse($lines, [
            [1, 'Unknown token "unknown_token" in "!#if" condition.'],
            [3, 'Unknown token "unknown" in "!#if" condition.'],
            [5, 'Unknown token "something_else" in "!#if" condition.'],
        ]);
    }

    #[PHPUnit\Test]
    public function mutually_exclusive_tokens(): void
    {
        $lines = [
            '!#if adguard && ext_ublock',
            '!#endif',

            '!#if env_firefox && env_chromium',
            '!#endif',
        ];
        $this->analyse($lines, [
            [1, 'Tokens "adguard" and "ext_ublock" will always evaluate to false.'],
            [3, 'Tokens "env_firefox" and "env_chromium" will always evaluate to false.'],
        ]);

        $lines = [
            '!#if env_firefox',
            '!#if env_chromium',
            '...',
            '!#endif',
            '!#endif',
        ];
        $this->analyse($lines, [
            [2, 'Token "env_chromium" will always evaluate to "false" with "env_firefox" from the parent "!#if" on line 1.'],
        ]);
    }
}
