<?php

namespace Realodix\Haiku\Linter\Rules;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\Util;

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
        if (!$this->config->rules['cosm_id_selector_start']
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
            foreach ($matches[0] as $match) {
                $msg = sprintf('Invalid filter: ID selector %s cannot start with a number.', $match);
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
    private function checkAbpExtendedCssSelectors($err, array $node): void
    {
        if (!str_contains($node['selector'], ':-abp-')
            || ($node['separator'] === '#?#' || $node['separator'] === '#@?#')
        ) {
            return;
        }

        preg_match('/-abp-(?:has|contains|properties)/', $node['selector'], $content);

        $err->message(sprintf(
            'Invalid filter: %s requires #?# separator syntax.',
            $content[0],
        ))->build();
    }
}
