<?php

namespace Realodix\Haiku\Linter\Rules\NetOptions;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Linter\Util;

final class PatternAnchorCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        if (!$this->config->rules['net_pattern_anchor']) {
            return [];
        }

        $errors = [];

        foreach ($content as $index => $line) {
            $line = trim($line);
            if (preg_match(Regex::IS_COSMETIC_RULE, $line)
                || Util::isCommentOrEmpty($line)
            ) {
                continue;
            }

            $hasException = str_starts_with($line, '@@');
            if ($hasException) {
                $line = substr($line, 2);
            }

            if (preg_match(Regex::NET_OPTION, $line, $m)) {
                $line = $m[1];

                if (preg_match('/^\|+/', $line)) {
                    $line = $line.'__boundary__';
                }
            }

            // Left anchor
            preg_match('/^\|+/', $line, $m);
            $leadingPipes = isset($m[0]) ? strlen($m[0]) : 0;

            if ($leadingPipes > 2) {
                $errors[] = RuleErrorBuilder::message('Too many "|" at the beginning (max 2 allowed).')
                    ->line($index + 1)->build();
            }

            // Right anchor
            preg_match('/\|+$/', $line, $m);
            $trailingPipes = isset($m[0]) ? strlen($m[0]) : 0;

            if ($trailingPipes > 1) {
                $errors[] = RuleErrorBuilder::message('Too many "|" at the end (only 1 allowed).')
                    ->line($index + 1)->build();
            }
        }

        return $errors;
    }
}
