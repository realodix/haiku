<?php

namespace Realodix\Haiku\Linter\Rules\Lines;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Linter\Util;

final class TooShortLineCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        $mode = $this->config->rules['no_short_rules'];

        if ($mode === false) {
            return [];
        }

        // Default line length
        if ($mode === true) {
            $mode = 3;
        }

        $errors = [];

        foreach ($content as $index => $line) {
            $line = trim($line);
            if (Util::isCommentOrEmpty($line)) {
                continue;
            }

            if (strlen($line) < $mode) {
                $errors[] = RuleErrorBuilder::message("The line is too short (under {$mode} characters).")
                    ->line($index + 1)
                    ->build();
            }
        }

        return $errors;
    }
}
