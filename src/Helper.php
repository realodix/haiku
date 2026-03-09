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
}
