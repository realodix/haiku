<?php

namespace Realodix\Haiku\Linter\Rules\Lines;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Rules\Rule;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 */
final class ExcessiveEmptyLinesCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        $mode = $this->config->rules['no_extra_blank_lines'];

        if ($mode === false) {
            return [];
        }

        $errors = [];
        $emptyLinesCount = 0;

        foreach ($content as $index => $line) {
            if (trim($line) === '') {
                $emptyLinesCount++;

                continue;
            }

            // Report empty lines found in the middle of file (between content).
            $this->reportIfExcessive($errors, $index - $emptyLinesCount + 1, $emptyLinesCount, $mode);

            $emptyLinesCount = 0;
        }

        // Report empty lines if they appear at the very end of the file.
        $this->reportIfExcessive($errors, count($content) - $emptyLinesCount + 1, $emptyLinesCount, $mode);

        return $errors;
    }

    /**
     * @param list<_RuleError> $errors
     */
    private function reportIfExcessive(array &$errors, int $lineNum, int $count, int $maxCount): void
    {
        if ($count > $maxCount) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Too many consecutive empty lines (%d), maximum allowed is %d.',
                $count,
                $maxCount,
            ))->line($lineNum)->build();
        }
    }
}
