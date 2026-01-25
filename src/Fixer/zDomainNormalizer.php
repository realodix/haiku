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

    public static function normalizeDomain(string $domain, string $separator, bool $xMode = false): string
    {
        // regex domain, don't touch
        if (str_starts_with($domain, '/') && str_ends_with($domain, '/')) {
            return $domain;
        }

        $domains = collect(explode($separator, $domain))
            ->filter(fn($d) => $d !== '')
            ->map(function ($str) {
                $domain = strtolower($str);

                return self::cleanDomain($domain);
            });

        // DomainCoverageReducer
        if ($xMode) {
            $domains = self::removeWildcardCoveredDomains($domains);
            $domains = self::removeSubdomainCoveredDomains($domains);
        }

        return $domains->unique()->sortBy(function ($str) {
            // ensure negated domains ('~') come first
            if (str_starts_with($str, '~')) {
                return '/'.$str;
            }

            return $str;
        })->implode($separator);
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

    /**
     * Remove explicit domains covered by wildcard TLD domains.
     *
     * example.com + example.*  → example.*
     *
     * @param \Illuminate\Support\Collection<int, string> $domains
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function removeWildcardCoveredDomains($domains)
    {
        // collect wildcard bases: example.*
        $wildcardBases = $domains
            ->filter(fn($d) => !str_starts_with($d, '~') && str_ends_with($d, '.*'))
            ->map(fn($d) => substr($d, 0, -2))
            ->unique();

        if ($wildcardBases->isEmpty()) {
            return $domains;
        }

        return $domains->reject(function ($d) use ($wildcardBases) {
            if (str_starts_with($d, '~') // don't touch negated domains
                || str_ends_with($d, '.*') // keep wildcard domains
                // although IP never uses wildcards, we assume that the input is not always correct
                || filter_var($d, FILTER_VALIDATE_IP) !== false
            ) {
                return false;
            }

            // example.com → example
            $base = explode('.', $d, 2)[0];

            return $wildcardBases->contains($base);
        });
    }

    /**
     * Remove subdomains covered by their base domain.
     *
     * example.com + login.example.com → example.com
     *
     * @param \Illuminate\Support\Collection<int, string> $domains
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function removeSubdomainCoveredDomains($domains)
    {
        // collect base domains (non-negated, non-wildcard)
        $baseDomains = $domains
            ->filter(fn($d) => !str_starts_with($d, '~')
                && !str_ends_with($d, '.*')
                && str_contains($d, '.'),
            )->unique();

        if ($baseDomains->isEmpty()) {
            return $domains;
        }

        return $domains->reject(function ($d) use ($baseDomains) {
            // don't touch negated domains
            if (str_starts_with($d, '~')) {
                return false;
            }

            foreach ($baseDomains as $base) {
                // skip self
                if ($d === $base) {
                    continue;
                }

                // login.example.com ends with .example.com
                if (str_ends_with($d, '.'.$base)) {
                    return true;
                }
            }

            return false;
        });
    }
}
