<?php

namespace Realodix\Haiku\Linter\Rules\Preprocessor;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Helper;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Rules\Rule;

final class ExpressionCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        if (!$this->config->rules['pp_value']) {
            return [];
        }

        $errors = [];

        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if (!preg_match('/^!#\s?if(?:\s+(.*)|$)/i', $line, $matches)) {
                continue;
            }

            $condition = trim($matches[1] ?? '');
            if ($condition === '') {
                $errors[] = RuleErrorBuilder::message('The "!#if" statement must have a condition.')
                    ->line($lineNum)
                    ->build();

                continue;
            }

            // Remove outer parentheses if they exist and are balanced
            if (str_starts_with($condition, '(') && str_ends_with($condition, ')')) {
                $stripped = substr($condition, 1, -1);
                if ($this->isBalanced($stripped)) {
                    $condition = $stripped;
                }
            }

            // Tokenize condition to find identifiers
            // Identifiers start with a letter or underscore, followed by letters, numbers, or underscores
            preg_match_all('/[a-zA-Z_][a-zA-Z0-9_]*/', $condition, $tokenMatches);

            if (empty($tokenMatches[0])) {
                $errors[] = RuleErrorBuilder::message('The "!#if" statement must have a condition.')
                    ->line($lineNum)
                    ->build();

                continue;
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

        return $errors;
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
