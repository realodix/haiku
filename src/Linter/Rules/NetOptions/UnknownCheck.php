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

                if ($actualName === 'xml') {
                    $errors[] = RuleErrorBuilder::message(sprintf('Invalid filter option: "%s".'))
                        ->line($index + 1)
                        ->tip('Did you mean "xhr"?')
                        ->build();

                    continue;
                }

                if (!in_array($actualName, $knownOptions, true)) {
                    $builder = RuleErrorBuilder::message(sprintf('Unknown filter option: "%s".', $actualName))
                        ->line($index + 1);

                    $hint = Helper::getSuggestion($knownOptions, $actualName);
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
            $prev = $i > 0 ? $optionString[$i - 1] : null;

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
                    // start regex
                    if ($prev === '=' || $prev === ',' || $prev === null) {
                        $inRegex = true;
                    }
                } else {
                    // end regex
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

    // For now, let's skip `$replace`
    // private function splitOptions(string $optionString): array
    // {
    //     $result = [];
    //     $buffer = '';
    //     $len = strlen($optionString);

    //     $inRegex = false;
    //     $bracketDepth = 0;
    //     $escaped = false;

    //     for ($i = 0; $i < $len; $i++) {
    //         $c = $optionString[$i];

    //         // Handle escape
    //         if ($escaped) {
    //             $buffer .= $c;
    //             $escaped = false;

    //             continue;
    //         }

    //         if ($c === '\\') {
    //             $buffer .= $c;
    //             $escaped = true;

    //             continue;
    //         }

    //         // Detect regex
    //         if ($c === '/' && $bracketDepth === 0) {
    //             $inRegex = !$inRegex;
    //             $buffer .= $c;

    //             continue;
    //         }

    //         // Track bracket depth
    //         if (!$inRegex) {
    //             if ($c === '[' || $c === '(') {
    //                 $bracketDepth++;
    //             } elseif ($c === ']' || $c === ')') {
    //                 $bracketDepth--;
    //             }
    //         }

    //         // Split only if safe
    //         if ($c === ',' && !$inRegex && $bracketDepth === 0) {
    //             $result[] = $buffer;
    //             $buffer = '';

    //             continue;
    //         }

    //         $buffer .= $c;
    //     }

    //     if ($buffer !== '') {
    //         $result[] = $buffer;
    //     }

    //     return $result;
    // }
}
