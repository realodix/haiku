<?php

namespace Realodix\Haiku\Linter\Rules\Redundant;

final class DomainCoverage
{
    /**
     * Find domains covered by another domain in the same domain list.
     *
     * Coverage rules:
     * - example.* covers example.com
     * - example.* covers ads.example.com
     * - example.com covers ads.example.com
     *
     * Negated domains, wildcard domains, and IP addresses are preserved.
     *
     * @param list<string> $domains
     * @return array<string, string> Redundant domain => covering domain
     */
    public static function findRedundant(array $domains): array
    {
        // Build lookup sets for wildcard TLD domains and regular domains.
        $wildcardBases = [];
        $baseSet = [];

        foreach ($domains as $domain) {
            if (
                $domain === ''
                || str_starts_with($domain, '~')
            ) {
                continue;
            }

            if (str_ends_with($domain, '.*')) {
                $wildcardBases[substr($domain, 0, -2)] = $domain;

                continue;
            }

            if (
                filter_var($domain, FILTER_VALIDATE_IP) === false
                && str_contains($domain, '.')
            ) {
                $baseSet[$domain] = true;
            }
        }

        $redundant = [];

        foreach ($domains as $domain) {
            if (
                $domain === ''
                || str_starts_with($domain, '~')
                || str_ends_with($domain, '.*')
                || filter_var($domain, FILTER_VALIDATE_IP) !== false
            ) {
                continue;
            }

            // Check wildcard TLD coverage iteratively.
            //
            // example.* covers:
            // - example.com
            // - ads.example.com
            // - login.ads.example.com
            $check = $domain;

            while (($dotPos = strpos($check, '.')) !== false) {
                $base = substr($check, 0, $dotPos);

                if (isset($wildcardBases[$base])) {
                    $redundant[$domain] = $wildcardBases[$base];

                    continue 2;
                }

                $check = substr($check, $dotPos + 1);
            }

            // Check regular parent-domain coverage.
            //
            // example.com covers:
            // - ads.example.com
            // - login.ads.example.com
            $parent = $domain;

            while (($dotPos = strpos($parent, '.')) !== false) {
                $parent = substr($parent, $dotPos + 1);

                if (isset($baseSet[$parent])) {
                    $redundant[$domain] = $parent;

                    break;
                }
            }
        }

        return $redundant;
    }
}
