<?php

namespace Realodix\Haiku\Linter\Rules\Preprocessor;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Helper;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Linter\Rules\Rule;

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

    public function check(array $content, $err): array
    {
        if (!$this->config->rules['pp_value']) {
            return [];
        }

        // Stack to track required value from parent "!#if" conditions.
        // This is used to detect conflicts in nested directives.
        /** @var list<array{lineNum: int, reqValue: list<string>}> */
        $stack = [];

        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $err->line($lineNum);
            $line = trim($line);

            if (preg_match('/^!#\s?if(?:\s+(.*)|$)/i', $line, $matches)) {
                $condition = trim($matches[1] ?? '');

                if ($condition === '') {
                    $err->message('The "!#if" statement must have a condition.')
                        ->build();

                    $stack[] = ['lineNum' => $lineNum, 'reqValue' => []];

                    continue;
                }

                if ($this->checkParenthesisError($err, $condition)) {
                    continue;
                }

                $this->checkUnknownValue($err, $condition);

                $required = $this->getRequiredValue($condition);
                $this->checkExclusive($err, $required);
                $this->checkNestedExclusive($err, $required, $stack);

                $stack[] = ['lineNum' => $lineNum, 'reqValue' => $required];

                continue;
            }

            if (preg_match('/^!#\s?else(?:\s+(.*)|$)/i', $line, $matches)) {
                $condition = trim($matches[1] ?? '');

                if ($condition !== '') {
                    $err->message('The "!#else" statement must not have a condition.')
                        ->build();
                }

                if (!empty($stack)) {
                    // Inside an "!#else" block, the parent "!#if" condition is no longer
                    // required to be true (it's actually false). We reset the required
                    // value for this stack frame to avoid false-positive exclusivity
                    // errors for nested directives.
                    $stack[count($stack) - 1]['reqValue'] = [];
                }

                continue;
            }

            if (preg_match('/^!#\s?endif/i', $line)) {
                // When reaching "!#endif", we exit the current directive scope by
                // removing the last frame from the stack.
                array_pop($stack);

                continue;
            }
        }

        return $err->toArray();
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function checkParenthesisError($err, string $condition): bool
    {
        $balance = substr_count($condition, '(') - substr_count($condition, ')');

        if ($balance === 0) {
            return false;
        }

        if ($balance < 0) {
            $err->message('Extra closing parenthesis without an opening one.')
                ->build();

            return true;
        }

        $err->message('Unclosed opening parenthesis.')
            ->build();

        return true;
    }

    /**
     * rNames:
     * - no-unknown-preprocessor-directives
     *
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function checkUnknownValue($err, string $condition): void
    {
        // Remove outer parentheses if they exist and are balanced
        if (str_starts_with($condition, '(') && str_ends_with($condition, ')')) {
            $condition = substr($condition, 1, -1);
        }

        // Tokenize condition to find identifiers
        preg_match_all('/[a-zA-Z_][a-zA-Z0-9_]*/', $condition, $valueMatches);

        if (empty($valueMatches[0])) {
            $err->message('The "!#if" statement must have a condition.')
                ->build();
        }

        $knownPreprocessorValues = Registry::PREPROCESSOR_DIRECTIVES;
        foreach ($valueMatches[0] as $value) {
            if (!in_array($value, $knownPreprocessorValues, true)) {
                $hint = Helper::getSuggestion($knownPreprocessorValues, $value);

                $err->message(sprintf('Unknown value "%s" in "!#if" condition.', $value))
                    ->when($hint, function () use ($err, $hint) {
                        $err->tip(sprintf('Did you mean "%s"?', $hint));
                    })->build();
            }
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param list<string> $required
     */
    private function checkExclusive($err, array $required): void
    {
        foreach (self::EXCLUSIVE_GROUPS as $group) {
            $intersect = array_intersect($required, $group);
            if (count($intersect) > 1) {
                $intersect = array_values($intersect);
                $err->message(sprintf(
                    '"%s" and "%s" will always evaluate to false.',
                    $intersect[0], $intersect[1],
                ))->build();
            }
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param list<string> $required
     * @param list<array{reqValue: list<string>, lineNum: int}> $stack
     */
    private function checkNestedExclusive($err, array $required, array $stack): void
    {
        // 1. Flatten all required values from the parent stack frames into a single lookup list.
        $parentRequired = array_merge(...array_column($stack, 'reqValue'));

        foreach ($required as $value) {
            // 2. Identify if the current value belongs to any pre-defined exclusive group.
            $group = array_find(self::EXCLUSIVE_GROUPS, fn($g) => in_array($value, $g, true));

            if (!$group) {
                continue;
            }

            // 3. Check for conflicting tokens: find any other member of the same
            // group already present in the parent context.
            $other = array_find(
                $group,
                fn($item) => $item !== $value && in_array($item, $parentRequired, true),
            );

            if ($other) {
                // 4. Trace back through the stack to find the nearest frame
                // containing the conflicting token for error reporting.
                $parentFrame = array_find(
                    array_reverse($stack),
                    fn($frame) => in_array($other, $frame['reqValue'], true),
                );

                $err->message(sprintf(
                    '"%s" will always evaluate to "false" with "%s" from the parent "!#if" on line %d.',
                    $value, $other, $parentFrame['lineNum'] ?? 0,
                ))->build();
            }
        }
    }

    /**
     * Get value that MUST be true for the condition to be true.
     *
     * @return list<string>
     */
    private function getRequiredValue(string $condition): array
    {
        $condition = trim($condition);
        if ($condition === '') {
            return [];
        }

        if (str_starts_with($condition, '(') && str_ends_with($condition, ')')) {
            $stripped = substr($condition, 1, -1);

            return $this->getRequiredValue($stripped);
        }

        $orParts = $this->splitByOperator($condition, '||');
        if (count($orParts) > 1) {
            $firstPart = $this->getRequiredValue(array_shift($orParts));
            $required = array_reduce($orParts, function (array $carry, string $part) {
                return array_intersect($carry, $this->getRequiredValue($part));
            }, $firstPart);

            return $required ? array_values($required) : [];
        }

        $andParts = $this->splitByOperator($condition, '&&');
        if (count($andParts) > 1) {
            $allRequired = array_map(fn($part) => $this->getRequiredValue($part), $andParts);

            return array_unique(array_merge(...$allRequired));
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
}
