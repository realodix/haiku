<?php

namespace Realodix\Haiku;

final class Helper
{
    /**
     * Returns a sorted, unique array of strings.
     *
     * @param array<int, string> $value
     * @return list<string>
     */
    public static function uniqueSortBy(array $value, ?callable $callback, int $flags = SORT_REGULAR): array
    {
        $v = array_filter($value, static fn($s) => $s !== '');
        $v = array_unique($v);

        // Sort by callback
        $results = [];
        foreach ($v as $key => $value) {
            $results[$key] = $callback($value, $key);
        }
        asort($results, $flags);
        foreach (array_keys($results) as $key) {
            $results[$key] = $v[$key];
        }

        return array_values($results);
    }

    /**
     * Determines if a given filter line is a cosmetic filter rule.
     *
     * @param string $line The filter rule to analyze
     * @return bool True if the rule is a cosmetic filter rule, false otherwise
     */
    public static function isCosmeticRule(string $line): bool
    {
        // https://regex101.com/r/OW1tkq/1
        $basic = preg_match('/^#@?#[^\s|\#]|^#@?##[^\s|\#]/', $line);
        // https://regex101.com/r/SPcKMv/1
        $advanced = preg_match('/^(#(?:@?(?:\$|\?|%)|@?\$\?)#)[^\s]/', $line);

        return $basic || $advanced;
    }

    /**
     * Joins an array of strings into a single string with line breaks.
     *
     * @param array<int, string> $lines The array of strings to join
     * @return string The joined string with line breaks
     */
    public static function joinLines(array $lines): string
    {
        return implode("\n", $lines)."\n";
    }

    /**
     * @param array<string, bool|string> $override
     * @return array<string, bool|string>
     */
    public static function deprecatedFlags(array $override): array
    {
        $renames = [
            'xmode' => 'fmode',
            'adg_non_basic_rules_modifiers' => 'adg_non_basic_rule_modifier',
            'normalize_domains' => 'normalize_domain',
        ];

        foreach ($renames as $old => $new) {
            if (array_key_exists($old, $override)) {
                $override[$new] = $override[$old];
                unset($override[$old]);
            }
        }

        return $override;
    }

    /**
     * Escapes a string of characters according to the CSS escape rules.
     *
     * https://github.com/tailwindlabs/tailwindcss/blob/a4be98386/packages/tailwindcss/src/utils/escape.ts
     *
     * @param string $str The string to escape
     * @return string The escaped string
     */
    public static function cssEscape(string $str): string
    {
        $length = strlen($str);
        $index = -1;
        $result = '';
        $firstCodeUnit = $length > 0 ? ord($str[0]) : null;

        // If the character is the first character and is a `-` (U+002D), and
        // there is no second character, […]
        if ($length === 1 && $firstCodeUnit === 0x002D) {
            return '\\'.$str;
        }

        while (++$index < $length) {
            $codeUnit = ord($str[$index]);
            // Note: there’s no need to special-case astral symbols, surrogate
            // pairs, or lone surrogates.

            // If the character is NULL (U+0000, byte 0x00), then the REPLACEMENT
            // CHARACTER (U+FFFD).
            if ($codeUnit === 0x0000) {
                $result .= "\u{FFFD}";

                continue;
            }

            if (
                // If the character is in the range [\1-\1F] (U+0001 to U+001F) or is U+007F
                // @phpstan-ignore greaterOrEqual.alwaysTrue
                ($codeUnit >= 0x0001 && $codeUnit <= 0x001F) || $codeUnit === 0x007F
                // If the character is the first character and is in the range [0-9]
                // (U+0030 to U+0039)
                || ($index === 0 && $codeUnit >= 0x0030 && $codeUnit <= 0x0039)
                // If the character is the second character and is in the range [0-9]
                // (U+0030 to U+0039) and the first character is a `-` (U+002D)
                || ($index === 1 && $codeUnit >= 0x0030 && $codeUnit <= 0x0039 && $firstCodeUnit === 0x002D)
            ) {
                // https://drafts.csswg.org/cssom/#escape-a-character-as-code-point
                $result .= '\\'.dechex($codeUnit).' ';

                continue;
            }

            // If the character is not handled by one of the above rules and is
            // greater than or equal to U+0080, is `-` (U+002D) or `_` (U+005F), or
            // is in one of the ranges [0-9] (U+0030 to U+0039), [A-Z] (U+0041 to
            // U+005A), or [a-z] (U+0061 to U+007A)
            if ($codeUnit >= 0x0080
                || $codeUnit === 0x002D
                || $codeUnit === 0x005F
                || ($codeUnit >= 0x0030 && $codeUnit <= 0x0039)
                || ($codeUnit >= 0x0041 && $codeUnit <= 0x005A)
                || ($codeUnit >= 0x0061 && $codeUnit <= 0x007A)
            ) {
                // the character itself
                $result .= $str[$index];

                continue;
            }

            // Otherwise, the escaped character
            // https://drafts.csswg.org/cssom/#escape-a-character
            $result .= '\\'.$str[$index];
        }

        return $result;
    }
}
