<?php

namespace Realodix\Haiku\Test\Linter\Rules\Preprocessor;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\Preprocessor\IfClosedCheck;
use Realodix\Haiku\Test\TestCase;

class IfClosedCheckTest extends TestCase
{
    private const RULE = [
        IfClosedCheck::class,
    ];

    #[PHPUnit\Test]
    public function valid(): void
    {
        $lines = [
            '!#if ext_ubol',
            'foo',
            '!#endif',
            '',
            '!#if ext_ubol',
            'foo',
            '!#else',
            'bar',
            '!#endif',
        ];
        $this->analyse($lines, onlyRules: self::RULE);

        // both if-s are closed properly
        $lines = [
            'rule',
            '!#if (condition1)',
            '!#if (condition2)',
            'rule',
            '!#endif',
            'rule',
            '!#endif',
            'rule',
        ];
        $this->analyse($lines, onlyRules: self::RULE);

        // 'include' directive inside 'if' block
        $lines = [
            'rule',
            '!#if (ext_ubol)',
            '!#include https://raw.example.com/file1.txt',
            '!#endif',
        ];
        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function unclosed_if(): void
    {
        $lines = [
            '!#if ext_ubol',
            'foo',
        ];
        $this->analyse($lines, [
            [1, 'Unclosed !#if directive.'],
        ]);

        $lines = [
            'rule',
            '!#if (condition1)',
            '!#if (condition2)',
            'rule',
            '!#endif',
            'rule',
            'rule',
        ];
        $this->analyse($lines, [
            [2, 'Unclosed !#if directive.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function unclosed_if_with_else(): void
    {
        $lines = [
            '!#if ext_ubol',
            'foo',
            '!#else',
            'bar',
        ];
        $this->analyse($lines, [
            [1, 'Unclosed !#if directive.'],
        ]);

        $lines = [
            'rule0',
            '!#if (condition1)',
            'rule1',
            '!#endif',
            '!#if (condition2)',
            'rule2',
            '!#else',
            'rule3',
        ];
        $this->analyse($lines, [
            [5, 'Unclosed !#if directive.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function endif_without_if(): void
    {
        $lines = [
            'foo',
            '!#endif',
        ];
        $this->analyse($lines, [
            [2, 'Unexpected !#endif without matching !#if.'],
        ]);

        $lines = [
            'rule',
            '!#if (ext_ubol)',
            'rule',
            '!#endif',
            '!#endif',
            'rule',
            'rule',
        ];
        $this->analyse($lines, [
            [5, 'Unexpected !#endif without matching !#if.'],
        ], self::RULE);
    }

    /**
     * Should detect unopened else directive
     */
    #[PHPUnit\Test]
    public function else_without_if(): void
    {
        $lines = [
            'rule1',
            '!#else',
            'rule2',
        ];
        $this->analyse($lines, [
            [2, 'Unexpected !#else without matching !#if.'],
            [2, 'Unclosed !#else directive.'],
        ]);

        $lines = [
            'foo',
            '!#else',
            'bar',
            '!#endif',
        ];
        $this->analyse($lines, [
            [2, 'Unexpected !#else without matching !#if.'],
        ]);

        $lines = [
            '!#if (ext_ubol)',
            'rule1',
            '!#endif',
            '!#else',
            'rule2',
            '!#endif',
        ];
        $this->analyse($lines, [
            [4, 'Unexpected !#else without matching !#if.'],
        ]);
    }

    #[PHPUnit\Test]
    public function multiple_else(): void
    {
        $lines = [
            '!#if ext_ubol',
            'foo',
            '!#else',
            'bar',
            '!#else',
            'baz',
            '!#endif',
        ];
        $this->analyse($lines, [
            [5, 'Multiple !#else directives for the same !#if.'],
        ]);
    }

    #[PHPUnit\Test]
    public function nested(): void
    {
        $lines = [
            '!#if ext_ublock',
            '!#if ext_ublock',
            '!#endif',
            '!#endif',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function nested_error(): void
    {
        $lines = [
            '!#if ext_ublock',
            '!#if ext_ublock',
            '!#endif',
        ];

        $this->analyse($lines, [
            [1, 'Unclosed !#if directive.'],
        ]);
    }
}
