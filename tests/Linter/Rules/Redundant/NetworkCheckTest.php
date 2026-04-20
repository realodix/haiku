<?php

namespace Realodix\Haiku\Test\Linter\Rules\Redundant;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Linter\Rules\Redundant\NetworkCheck;
use Realodix\Haiku\Test\TestCase;

class NetworkCheckTest extends TestCase
{
    private const RULE = [NetworkCheck::class];

    #[PHPUnit\Test]
    public function network_rules_case_insensitive(): void
    {
        $lines = [
            '||example.com^',
            '||EXAMPLE.COM^',
            '||example.org^$script',
            '||example.org^$SCRIPT',
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: ||EXAMPLE.COM^ already defined on line 1.'],
            [4, 'Redundant filter: ||example.org^$SCRIPT already defined on line 3.'],
        ], self::RULE);
    }

    #[PHPUnit\Test]
    public function redundant(): void
    {
        $lines = [
            '||example.com^',
            '||example.com^$script', // Redundant
        ];

        $this->analyse($lines, [
            [2, 'Redundant filter: ||example.com^$script already covered by ||example.com^ on line 1.'],
        ]);
    }

    #[PHPUnit\Test]
    public function redundant_exclude_popup(): void
    {
        $lines = [
            '/ads/*$popup',
            '/ads/*',
            '||example.com^',
            '||example.com^$popup',
        ];

        $this->analyse($lines);
    }

    #[PHPUnit\Test]
    public function ignore_comments_and_directives(): void
    {
        $lines = [
            '! Comment',
            '!#if ext_abp',
            '||example.com^',
            '||example.com^',
        ];

        $this->analyse($lines, [
            [4, 'Redundant filter: ||example.com^ already defined on line 3.'],
        ], self::RULE);
    }
}
