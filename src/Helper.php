<?php

namespace Realodix\Haiku;

final class Helper
{
    /**
     * Returns a sorted, unique array of strings.
     *
     * @param array<string> $value The array of strings to process
     * @param callable|null $sortBy The sorting function to use
     * @param int $flags The sorting flags
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function uniqueSorted(array $value, ?callable $sortBy = null, $flags = SORT_REGULAR)
    {
        $c = collect($value)
            ->filter(fn($s) => $s !== '')
            ->unique();

        $c = is_callable($sortBy)
            ? $c->sortBy($sortBy, $flags)
            : $c->sort();

        return $c->values();
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

    public static function normalizeDomain(string $domain, string $separator): string
    {
        if (self::containsRegexDomain($domain)) {
            return $domain;
        }

        $domain = explode($separator, $domain);
        $domain = collect($domain)
            ->filter(fn($d) => $d !== '')
            ->map(function ($str) {
                if (self::containsRegexDomain($str)) {
                    return $str;
                }

                $domain = strtolower($str);
                $domain = self::cleanDomain($domain);

                return $domain;
            })->unique()
            ->sortBy(fn($d) => ltrim($d, '~'))
            ->implode($separator);

        return $domain;
    }

    public static function cleanDomain(string $domain): string
    {
        $domain = trim($domain);

        if (str_starts_with($domain, '/') || str_starts_with($domain, '.')) {
            $domain = substr($domain, 1);
        }

        if (str_ends_with($domain, '/')) {
            $domain = substr($domain, 0, -1);
        }

        return $domain;
    }

    public static function containsRegexDomain(string $domain): bool
    {
        return str_contains($domain, '/') && preg_match('/[\\^([{$\\\]/', $domain);
    }
}
