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
        $domainSet = array_fill_keys($domains, true);
        $redundant = [];

        foreach ($domains as $domain) {
            unset($domainSet[$domain]);
            $coveringDomain = self::getCoveringDomain($domain, $domainSet);
            if ($coveringDomain !== null) {
                $redundant[$domain] = $coveringDomain;
            }

            $domainSet[$domain] = true;
        }

        return $redundant;
    }

    /**
     * Find the domain that covers the given domain.
     *
     * Coverage rules:
     * - example.* covers example.com
     * - example.* covers ads.example.com
     * - example.com covers ads.example.com
     *
     * @param array<string, bool> $candidateDomains
     */
    public static function getCoveringDomain(string $domain, array $candidateDomains): ?string
    {
        if (
            str_starts_with($domain, '~')
            // reduce the candidates
            || str_ends_with($domain, '.*')
            || filter_var($domain, FILTER_VALIDATE_IP) !== false
        ) {
            return null;
        }

        $parent = $domain;
        while (($dotPos = strpos($parent, '.')) !== false) {
            $base = substr($parent, 0, $dotPos);

            // example.* covers example.com, ads.example.com,
            // and deeper subdomains.
            $wildcardDomain = $base.'.*';

            if (isset($candidateDomains[$wildcardDomain])) {
                return $wildcardDomain;
            }

            $parent = substr($parent, $dotPos + 1);
        }

        // example.com covers ads.example.com and deeper subdomains
        $parent = $domain;
        while (($dotPos = strpos($parent, '.')) !== false) {
            $parent = substr($parent, $dotPos + 1);
            if (isset($candidateDomains[$parent])) {
                return $parent;
            }
        }

        return null;
    }
}
