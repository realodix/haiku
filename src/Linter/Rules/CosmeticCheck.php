<?php

namespace Realodix\Haiku\Linter\Rules;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Support\Util;

final class CosmeticCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content, $err): array
    {
        foreach ($content as $index => $line) {
            $err->line($index + 1);
            $line = trim($line);

            if (Util::isCommentOrEmpty($line) || str_starts_with($line, '[$')) {
                continue;
            }

            if (!preg_match(Regex::COSMETIC_RULE, $line, $m)) {
                continue;
            }

            $node = [
                'separator' => $m[4], // ##
                'selector' => $m[5],  // .ads
            ];

            $this->checkIdSelectorStartsWithDigit($err, $node);
            $this->checkColonEscape($err, $node);
            $this->checkAbpExtendedCssSelectors($err, $node);
        }

        return $err->toArray();
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param array<string, string> $node
     */
    private function checkIdSelectorStartsWithDigit($err, array $node): void
    {
        if (!$this->config->rules['no_invalid_id_selectors']
            || !($node['separator'] === '##' || $node['separator'] === '#@#')
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
            $node['selector'],
        );

        if (preg_match_all('/(?<!\\\)#[0-9][\w-]*/', $cleanSelector, $matches)) {
            foreach ($matches[0] as $m) {
                $msg = sprintf('Invalid filter: ID selector %s cannot start with a number.', $m);
                $err->message($msg)
                    ->tip('Escape the first digit using its Unicode code point or use another character.')
                    ->link('https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Values/ident#escaping_characters')
                    ->build();
            }
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param array<string, string> $node
     */
    private function checkColonEscape($err, array $node): void
    {
        if (!($node['separator'] === '##' || $node['separator'] === '#@#')) {
            return;
        }

        // https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Selectors/Pseudo-classes
        $selector = preg_replace(
            '/
                :.+\(                                   # has(), is(), etc
                |:(any|first|focus|in|last|only|user)-
                |\[[^\]]+\]
                |{.+}
            /x',
            '_',
            $node['selector'],
        );

        // Attribute selectors are replaced with "_" above, so "_" may appear immediately
        // before an escaped colon and must be treated as a valid preceding character.
        if (preg_match_all(
            '/(?<=[a-z\d\)_])(?<escape>[\\\]+)?:(?<name>[a-z]+-(?:[a-z\d\-]+))/',
            $selector,
            $matches,
        ) === 0) {
            return;
        }

        foreach ($matches[0] as $i => $value) {
            $escape = $matches['escape'][$i];
            $name = $matches['name'][$i];
            $match = $escape.':'.$name;

            if ($escape === '') {
                $err->message(sprintf(
                    'Invalid filter: Colon "%s" must be escaped with a backslash.',
                    $match,
                ))->build();
            }

            if (strlen($escape) > 1) {
                $err->message(sprintf(
                    'Invalid filter: Colon "%s" has too many backslashes.',
                    $match,
                ))->build();
            }
        }
    }

    /**
     * rNames:
     * - no_invalid_abp_extended_css_selectors
     *
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param array<string, string> $node
     */
    private function checkAbpExtendedCssSelectors($err, array $node): void
    {
        if (!str_contains($node['selector'], ':-abp-')
            || ($node['separator'] === '#?#' || $node['separator'] === '#@?#')
        ) {
            return;
        }

        if (preg_match('/-abp-(?:has|contains|properties)/', $node['selector'], $content) !== 1) {
            return;
        }

        $err->message(sprintf(
            'Invalid filter: %s requires #?# separator syntax.',
            $content[0],
        ))->build();
    }
}
