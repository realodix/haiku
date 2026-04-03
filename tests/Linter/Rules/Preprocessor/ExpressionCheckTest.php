<?php

namespace Realodix\Haiku\Test\Linter\Rules\Preprocessor;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\Preprocessor\ExpressionCheck;
use Realodix\Haiku\Test\TestCase;

class ExpressionCheckTest extends TestCase
{
    private const RULE = [
        ExpressionCheck::class,
    ];

    #[PHPUnit\Test]
    public function valid_directives(): void
    {
        $lines = [
            '!#if (adguard)',
            '!#if adguard',
            '!#if (adguard && !adguard_ext_safari)',
            '!#if adguard && !adguard_ext_safari',
            '!#if adguard || ext_ublock',
            '!#if ext_ubol',
            '!#if ext_abp',
        ];

        $this->analyse($lines, onlyRules: self::RULE);
    }

    #[PHPUnit\Test]
    public function empty_condition(): void
    {
        $lines = [
            '!#if',
            '!#if ',
            '!#if ( )',
        ];

        $this->analyse($lines, [
            [1, 'The "!#if" statement must have a condition.'],
            [2, 'The "!#if" statement must have a condition.'],
            [3, 'The "!#if" statement must have a condition.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function unknown_tokens(): void
    {
        $lines = [
            '!#if unknown_token',
            '!#if adguard && unknown',
            '!#if (adguard || ext_ublock) && something_else',
        ];

        $this->analyse($lines, [
            [1, 'Unknown token "unknown_token" in "!#if" condition.'],
            [2, 'Unknown token "unknown" in "!#if" condition.'],
            [3, 'Unknown token "something_else" in "!#if" condition.'],
        ], self::RULE);
    }
}
