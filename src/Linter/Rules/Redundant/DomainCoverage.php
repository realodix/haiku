<?php

namespace Realodix\Haiku\Linter\Rules\Redundant;

final class DomainCoverage
{
    /**
     * Find domains covered by another domain in the same domain list.
     *
     * @param list<string> $domains
     * @return array<string, string> Redundant domain => covering domain
     */
    public static function findRedundant(array $domains): array
    {
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

            // example.com is covered by example.*
            $dotPos = strpos($domain, '.');
            if ($dotPos !== false) {
                $base = substr($domain, 0, $dotPos);

                if (isset($wildcardBases[$base])) {
                    $redundant[$domain] = $wildcardBases[$base];

                    continue;
                }
            }

            // ads.example.com is covered by example.com
            $parent = $domain;

            while (($pos = strpos($parent, '.')) !== false) {
                $parent = substr($parent, $pos + 1);

                if (isset($baseSet[$parent])) {
                    $redundant[$domain] = $parent;

                    break;
                }
            }
        }

        return $redundant;
    }
}
