<?php

namespace Realodix\Haiku\Linter\Rules\NetOptions;

use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Helper;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Linter\Util;

final class UnknownCheck implements Rule
{
    public function check(array $content): array
    {
        $errors = [];
        $knownOptions = array_merge(Registry::OPTIONS, Registry::AG_OPTIONS);

        foreach ($content as $index => $line) {
            if ((!preg_match(Regex::NET_OPTION, $line, $m) || preg_match(Regex::IS_COSMETIC_RULE, $line))
                || Util::isCommentOrEmpty($line)
                // || str_contains($line, 'replace=')
            ) {
                continue;
            }

            // Group 2 contains the options string
            $optionsString = $m[2];
            $options = $this->splitOptions($optionsString);

            foreach ($options as $option) {
                $parts = explode('=', $option, 2);
                $actualName = strtolower(ltrim($parts[0], '~'));

                if ($actualName === ''
                    // noop option
                    || preg_match('/^_+$/', $actualName) === 1
                ) {
                    continue;
                }

                if (!in_array($actualName, $knownOptions, true)) {
                    $builder = RuleErrorBuilder::message(sprintf('Unknown filter option: "%s".', $actualName))
                        ->line($index + 1);

                    $hint = Helper::getSuggestion($knownOptions, $actualName);
                    if ($hint) {
                        $builder->tip(sprintf('Did you mean "%s"?', $hint));
                    } elseif ($actualName === 'xml') {
                        $builder->tip('Did you mean "xhr"?');
                    }

                    $errors[] = $builder->build();
                }
            }
        }

        return $errors;
    }

    /**
     * Splits a network filter's options.
     *
     * @return list<string>
     */
    private function splitOptions(string $optionString): array
    {
        $result = [];
        $buffer = '';
        $len = strlen($optionString);

        $inRegex = false;
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $escaped = false;

        for ($i = 0; $i < $len; $i++) {
            $c = $optionString[$i];

            // escape
            if ($escaped) {
                $buffer .= $c;
                $escaped = false;

                continue;
            }

            if ($c === '\\') {
                $buffer .= $c;
                $escaped = true;

                continue;
            }

            // string handling
            if ($c === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
                $buffer .= $c;

                continue;
            }

            if ($c === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
                $buffer .= $c;

                continue;
            }

            // regex handling
            if (!$inSingleQuote && !$inDoubleQuote && $c === '/') {
                if (!$inRegex) {
                    $inRegex = true;
                } elseif ($inRegex && $this->isRegexEnd($optionString, $i)) {
                    $inRegex = false;
                }

                $buffer .= $c;

                continue;
            }

            // split
            if ($c === ',' && !$inRegex && !$inSingleQuote && !$inDoubleQuote) {
                $result[] = $buffer;
                $buffer = '';

                continue;
            }

            $buffer .= $c;
        }

        if ($buffer !== '') {
            $result[] = $buffer;
        }

        return $result;
    }

    private function isRegexEnd(string $str, int $i): bool
    {
        $len = strlen($str);

        // Must be a forward slash
        if ($str[$i] !== '/') {
            return false;
        }

        // Do not treat escaped slash as regex end
        if ($i > 0 && $str[$i - 1] === '\\') {
            return false;
        }

        $j = $i + 1;

        // Skip regex flags (e.g. i, g, m, s, u)
        while ($j < $len && ctype_alpha($str[$j])) {
            $j++;
        }

        // A regex is considered closed if:
        // - we reached the end of the string
        // - the next meaningful character is a comma (next option)
        // - the next character is '$' (end anchor in filter syntax)
        return $j >= $len || $str[$j] === ',' || $str[$j] === '$';
    }
}
