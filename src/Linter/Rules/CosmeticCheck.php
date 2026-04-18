<?php

namespace Realodix\Haiku\Linter\Rules;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Util;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 */
final class CosmeticCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        $errors = [];

        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if (Util::isCommentOrEmpty($line) || str_starts_with($line, '[$')) {
                continue;
            }

            if (!preg_match(Regex::COSMETIC_RULE, $line, $m)) {
                continue;
            }

            $separator = $m[4]; // ##
            $selector = $m[5];  // .ads

            $this->checkIdSelectorStartsWithDigit($errors, $lineNum, $selector, $separator);
            $this->checkAbpExtendedCssSelectors($errors, $lineNum, $selector, $separator);
        }

        return $errors;
    }

    /**
     * @param list<_RuleError> $errors
     */
    private function checkIdSelectorStartsWithDigit(
        array &$errors,
        int $lineNum,
        string $selector,
        string $separator,
    ): void {
        if (!$this->config->rules['cosm_id_selector_start']
            || !($separator === '##' || $separator === '#@#')
        ) {
            return;
        }

        $cleanSelector = preg_replace(
            '/
                \[[^\]]+\]                  # attribute [...]
                |:(style)\s*\(.+\)          # :style(...)
                |:\s?\#[a-zA-Z\d]+\s?(;|!)  # :#hexcolor; atau :#hexcolor!
            /x',
            '',
            $selector,
        );

        if (preg_match_all('/(?<!\\\)#[0-9][\w-]*/', $cleanSelector, $matches)) {
            foreach ($matches[0] as $match) {
                $msg = sprintf('Invalid filter: ID selector %s cannot start with a number.', $match);
                $errors[] = RuleErrorBuilder::message($msg)
                    ->line($lineNum)
                    ->tip('Escape the first digit using its Unicode code point or use another character.')
                    ->link('https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Values/ident#escaping_characters')
                    ->build();
            }
        }
    }

    /**
     * @param list<_RuleError> $errors
     */
    private function checkAbpExtendedCssSelectors(
        array &$errors,
        int $lineNum,
        string $selector,
        string $separator,
    ): void {
        if (str_contains($selector, ':-abp-')
            && !($separator === '#?#' || $separator === '#@?#')
        ) {
            preg_match('/-abp-(?:has|contains|properties)/', $selector, $content);

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Invalid filter: %s requires #?# separator syntax.', $content[0]),
            )->line($lineNum)->build();
        }
    }
}
