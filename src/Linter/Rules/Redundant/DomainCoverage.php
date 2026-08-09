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
    public static function findCovered(array $domains): array
    {
        $domainSet = array_fill_keys($domains, true);
        $redundant = [];

        foreach ($domains as $domain) {
            unset($domainSet[$domain]);
            $coveringDomain = self::findCovering($domain, $domainSet);
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
    public static function findCovering(string $domain, array $candidateDomains): ?string
    {
        if (str_starts_with($domain, '~')
            // reduce the candidates
            || str_ends_with($domain, '.*')
            || filter_var($domain, FILTER_VALIDATE_IP) !== false
        ) {
            return null;
        }

        $covering = null;
        $coveringLength = PHP_INT_MAX;

        // 1. Check wildcard matches from parent segments.
        $parent = $domain;
        while (($dotPos = strrpos($parent, '.')) !== false) {
            $base = substr($parent, 0, $dotPos);
            $wildcardDomain = $base.'.*';
            if (isset($candidateDomains[$wildcardDomain]) && strlen($wildcardDomain) < $coveringLength) {
                $covering = $wildcardDomain;
                $coveringLength = strlen($wildcardDomain);
            }

            $firstDot = strpos($parent, '.');
            if ($firstDot === $dotPos) {
                break;
            }

            $parent = substr($parent, $firstDot + 1);
        }

        // 2. Check exact parent domains.
        $parent = $domain;
        while (($dotPos = strpos($parent, '.')) !== false) {
            $parent = substr($parent, $dotPos + 1);

            if (isset($candidateDomains[$parent]) && strlen($parent) < $coveringLength) {
                $covering = $parent;
                $coveringLength = strlen($parent);
            }
        }

        return $covering;
    }

    /**
     * Find domains covered by another domain in another list.
     *
     * @param array<string, bool> $a Domains of A
     * @param array<string, bool> $b Domains of B
     */
    public static function coversRuleDomains(array $a, array $b, bool $genericOnly = false): bool
    {
        // If A is empty, it represents global context, which covers everything
        if ($a === []) {
            return true;
        }

        // If B is empty but A is not, A cannot cover B
        if ($b === []) {
            return false;
        }

        $hasGeneric = false;
        foreach ($b as $domain => $_) {
            $covering = self::findCovering($domain, $a);

            if (!isset($a[$domain]) && $covering === null) {
                return false;
            }

            if ($covering !== null) {
                $hasGeneric = true;
            }
        }

        return $genericOnly ? $hasGeneric : true;
    }
}
