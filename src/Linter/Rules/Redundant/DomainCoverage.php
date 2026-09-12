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
     * Coverage is only evaluated between domains with the same marker combination.
     * `example.com`, `~example.com`, and `example.com>>` are three distinct entities
     * that never cover each other.
     *
     * @param array<string, bool> $candidateDomains
     */
    public static function findCovering(string $domain, array $candidateDomains): ?string
    {
        if (str_contains($domain, '.*')
            || filter_var($domain, FILTER_VALIDATE_IP) !== false
        ) {
            return null;
        }

        [$baseDomain, $prefix, $suffix] = self::splitDomainMarkers($domain);

        // Restrict candidates to the same marker namespace.
        $candidates = [];
        foreach ($candidateDomains as $cand => $_) {
            [$candBase, $candPrefix, $candSuffix] = self::splitDomainMarkers($cand);
            if ($candPrefix !== $prefix || $candSuffix !== $suffix) {
                continue;
            }
            $candidates[$candBase] = true;
        }

        if ($candidates === []) {
            return null;
        }

        $covering = null;
        $coveringLength = PHP_INT_MAX;

        // 1. Check wildcard matches from parent segments.
        $parent = $baseDomain;
        while (($dotPos = strrpos($parent, '.')) !== false) {
            $base = substr($parent, 0, $dotPos);
            $wildcardDomain = $base.'.*';
            if (isset($candidates[$wildcardDomain]) && strlen($wildcardDomain) < $coveringLength) {
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
        $parent = $baseDomain;
        while (($dotPos = strpos($parent, '.')) !== false) {
            $parent = substr($parent, $dotPos + 1);

            if (isset($candidates[$parent]) && strlen($parent) < $coveringLength) {
                $covering = $parent;
                $coveringLength = strlen($parent);
            }
        }

        return $covering === null ? null : $prefix.$covering.$suffix;
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

    /**
     * Split a domain string into its marker namespace and base hostname.
     *
     * @return array{0: string, 1: string, 2: string} [base, prefix, suffix]
     */
    private static function splitDomainMarkers(string $domain): array
    {
        $prefix = '';
        if (str_starts_with($domain, '~')) {
            $prefix = '~';
            $domain = substr($domain, 1);
        }

        $suffix = '';
        if (str_ends_with($domain, '>>')) {
            $suffix = '>>';
            $domain = substr($domain, 0, -2);
        }

        return [$domain, $prefix, $suffix];
    }
}
