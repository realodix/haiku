<?php

namespace Realodix\Haiku\Linter\Rules\Preprocessor;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Helper;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Rules\Rule;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 */
final class ExpressionCheck implements Rule
{
    private const EXCLUSIVE_GROUPS = [
        ['adguard', 'ext_abp', 'ext_ublock'],
        ['env_chromium', 'env_edge', 'env_firefox', 'env_safari',
            'adguard_ext_chromium', 'adguard_ext_edge', 'adguard_ext_firefox', 'adguard_ext_opera', 'adguard_ext_safari',
            'adguard_app_android', 'adguard_app_ios', 'adguard_app_mac', 'adguard_app_windows', 'adguard_ext_android_cb'],
    ];

    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        if (!$this->config->rules['pp_value']) {
            return [];
        }

        $errors = [];
        $stack = [];

        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if (preg_match('/^!#\s?if(?:\s+(.*)|$)/i', $line, $matches)) {
                $condition = trim($matches[1] ?? '');

                if ($condition === '') {
                    $errors[] = RuleErrorBuilder::message('The "!#if" statement must have a condition.')
                        ->line($lineNum)
                        ->build();

                    $stack[] = ['required' => [], 'line' => $lineNum];

                    continue;
                }

                $this->checkUnknownTokens($errors, $lineNum, $condition);

                $required = $this->getRequiredTokens($condition);
                $this->checkExclusive($errors, $lineNum, $required);
                $this->checkNestedExclusive($errors, $lineNum, $required, $stack);

                $stack[] = ['required' => $required, 'line' => $lineNum];

                continue;
            }

            if (preg_match('/^!#\s?else/i', $line)) {
                if (!empty($stack)) {
                    $stack[count($stack) - 1]['required'] = [];
                }

                continue;
            }

            if (preg_match('/^!#\s?endif/i', $line)) {
                array_pop($stack);

                continue;
            }
        }

        return $errors;
    }

    /**
     * rNames:
     * - no-unknown-preprocessor-directives
     *
     * @param list<_RuleError> $errors
     */
    private function checkUnknownTokens(array &$errors, int $lineNum, string $condition): void
    {
        // Remove outer parentheses if they exist and are balanced
        if (str_starts_with($condition, '(') && str_ends_with($condition, ')')) {
            $stripped = substr($condition, 1, -1);
            if ($this->isBalanced($stripped)) {
                $condition = $stripped;
            }
        }

        // Tokenize condition to find identifiers
        preg_match_all('/[a-zA-Z_][a-zA-Z0-9_]*/', $condition, $tokenMatches);

        if (empty($tokenMatches[0])) {
            $errors[] = RuleErrorBuilder::message('The "!#if" statement must have a condition.')
                ->line($lineNum)
                ->build();
        }

        foreach ($tokenMatches[0] as $token) {
            $knownPreprocessorTokens = Registry::PREPROCESSOR_DIRECTIVES;

            if (!in_array($token, $knownPreprocessorTokens, true)) {
                $builder = RuleErrorBuilder::message(sprintf('Unknown token "%s" in "!#if" condition.', $token))
                    ->line($lineNum);

                $hint = Helper::getSuggestion($knownPreprocessorTokens, $token);
                if ($hint) {
                    $builder->tip(sprintf('Did you mean "%s"?', $hint));
                }

                $errors[] = $builder->build();
            }
        }
    }

    /**
     * @param list<_RuleError> $errors
     * @param list<string> $required
     */
    private function checkExclusive(array &$errors, int $lineNum, array $required): void
    {
        foreach (self::EXCLUSIVE_GROUPS as $group) {
            $intersect = array_intersect($required, $group);
            if (count($intersect) > 1) {
                $intersect = array_values($intersect);
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Tokens "%s" and "%s" will always evaluate to false.',
                    $intersect[0], $intersect[1],
                ))->line($lineNum)->build();
            }
        }
    }

    /**
     * @param list<_RuleError> $errors
     * @param list<string> $required
     * @param list<array{required: list<string>, line: int}> $stack
     */
    private function checkNestedExclusive(array &$errors, int $lineNum, array $required, array $stack): void
    {
        $parentRequired = [];

        foreach ($stack as $frame) {
            $parentRequired = array_merge($parentRequired, $frame['required']);
        }
        $parentRequired = array_unique($parentRequired);

        foreach ($required as $token) {
            foreach (self::EXCLUSIVE_GROUPS as $group) {
                if (in_array($token, $group, true)) {
                    $others = array_intersect($parentRequired, $group);
                    $others = array_diff($others, [$token]);
                    if (!empty($others)) {
                        $other = array_shift($others);

                        $parentLine = 0;
                        foreach (array_reverse($stack) as $frame) {
                            if (in_array($other, $frame['required'], true)) {
                                $parentLine = $frame['line'];
                                break;
                            }
                        }

                        $errors[] = RuleErrorBuilder::message(sprintf(
                            'Token "%s" will always evaluate to "false" with "%s" from the parent "!#if" on line %d.',
                            $token, $other, $parentLine,
                        ))->line($lineNum)->build();
                    }
                }
            }
        }
    }

    /**
     * Get tokens that MUST be true for the condition to be true.
     *
     * @return list<string>
     */
    private function getRequiredTokens(string $condition): array
    {
        $condition = trim($condition);
        if ($condition === '') {
            return [];
        }

        if (str_starts_with($condition, '(') && str_ends_with($condition, ')')) {
            $stripped = substr($condition, 1, -1);
            if ($this->isBalanced($stripped)) {
                return $this->getRequiredTokens($stripped);
            }
        }

        $orParts = $this->splitByOperator($condition, '||');
        if (count($orParts) > 1) {
            $required = null;
            foreach ($orParts as $part) {
                $partRequired = $this->getRequiredTokens($part);
                if ($required === null) {
                    $required = $partRequired;
                } else {
                    $required = array_intersect($required, $partRequired);
                }
            }

            return $required ? array_values($required) : [];
        }

        $andParts = $this->splitByOperator($condition, '&&');
        if (count($andParts) > 1) {
            $required = [];
            foreach ($andParts as $part) {
                $required = array_merge($required, $this->getRequiredTokens($part));
            }

            return array_unique($required);
        }

        if (preg_match('/^(!?)([a-zA-Z_][a-zA-Z0-9_]*)$/', $condition, $matches)) {
            if ($matches[1] === '!') {
                return [];
            }

            return [$matches[2]];
        }

        return [];
    }

    /**
     * Split a string by an operator, respecting parentheses.
     *
     * @return list<string>
     */
    private function splitByOperator(string $str, string $op): array
    {
        $parts = [];
        $current = '';
        $balance = 0;
        $len = strlen($str);
        $opLen = strlen($op);

        for ($i = 0; $i < $len; $i++) {
            if ($str[$i] === '(') {
                $balance++;
            } elseif ($str[$i] === ')') {
                $balance--;
            }

            if ($balance === 0 && substr($str, $i, $opLen) === $op) {
                $parts[] = $current;
                $current = '';
                $i += $opLen - 1;

                continue;
            }

            $current .= $str[$i];
        }

        $parts[] = $current;

        return array_map('trim', $parts);
    }

    /**
     * Check if parentheses are balanced in the string.
     */
    private function isBalanced(string $str): bool
    {
        $balance = 0;
        for ($i = 0; $i < strlen($str); $i++) {
            if ($str[$i] === '(') {
                $balance++;
            } elseif ($str[$i] === ')') {
                $balance--;
            }

            if ($balance < 0) {
                return false;
            }
        }

        return $balance === 0;
    }
}
