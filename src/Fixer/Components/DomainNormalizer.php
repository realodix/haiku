<?php

namespace Realodix\Haiku\Fixer\Components;

use Realodix\Haiku\Config\FixerConfig;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Support\Arr;
use Realodix\Haiku\Support\Util;

final class DomainNormalizer
{
    /**
     * Getting a modifier that applies a domain.
     */
    public ?string $modifier = null;

    public function __construct(
        private FixerConfig $config,
    ) {}

    /**
     * @param ','|'|' $separator Domain separator (`,` or `|`)
     */
    public function applyFix(string $domainStr, string $separator, bool $caseSensitive = false): string
    {
        // Regex domain, don't touch
        if (Util::containsRegex($domainStr)) {
            return $domainStr;
        }

        $domainStr = $this->fixWrongSeparator($domainStr, $separator);

        $domains = explode($separator, $domainStr);
        foreach ($domains as $key => $d) {
            $d = trim($d);

            if ($d === '') {
                unset($domains[$key]);

                continue;
            }

            $domains[$key] = $this->normalizeDomain($d, $caseSensitive);
        }

        $domains = $this->removeWildcardCoveredDomains($domains);
        $domains = $this->removeCoveredSubdomains($domains);
        $domains = array_unique($domains);
        $domains = Arr::sortBy($domains, fn(string $value) => $this->domainSortKey($value));

        return implode($separator, $domains);
    }

    /**
     * Normalize incorrect separators in a domain list.
     *
     * If the domain string contains an incorrect separator (e.g. '|' instead of ','),
     * it will be replaced with the expected separator.
     *
     * @param string $domainStr The domain string to fix
     * @param string $separator The correct separator to use
     * @return string The fixed domain string
     */
    private function fixWrongSeparator(string $domainStr, string $separator): string
    {
        if (!$this->config->flags['fix_domain_separators']) {
            return $domainStr;
        }

        $typo = match ($separator) {
            '|' => ',',
            ',' => '|',
            default => throw new \InvalidArgumentException("Unknown separator '$separator'"),
        };

        if (str_contains($domainStr, $typo)) {
            $domainStr = str_replace($typo, $separator, $domainStr);
        }

        return $domainStr;
    }

    /**
     * Normalize a single domain entry.
     */
    private function normalizeDomain(string $domain, bool $caseSensitive): string
    {
        if (!$caseSensitive) {
            $domain = strtolower($domain);
        }

        if (str_starts_with($domain, '/') || str_starts_with($domain, '.')) {
            $domain = substr($domain, 1);
        }

        if (str_ends_with($domain, '/')) {
            $domain = substr($domain, 0, -1);
        }

        return $domain;
    }

    /**
     * Remove domains covered by wildcard TLD domain.
     *
     * Example:
     *   example.* + example.com -> keep example.*
     *
     * Negated domains and wildcard entries themselves are preserved.
     *
     * @param array<int, string> $domains
     * @return array<int, string>
     */
    private function removeWildcardCoveredDomains(array $domains)
    {
        if (!$this->config->flags['remove_wildcard_covered_domains']) {
            return $domains;
        }

        // Build lookup set of wildcard prefixes, grouped by negation and suffix.
        $wildcardBases = [];
        foreach ($domains as $d) {
            if (!str_ends_with(ltrim($d, '~'), '.*')
                && !str_ends_with(ltrim($d, '~'), '.*>>')
            ) {
                continue;
            }

            $isNegated = str_starts_with($d, '~');
            $isAncestor = str_ends_with($d, '>>');
            $domain = substr($d, $isNegated ? 1 : 0);

            if ($isAncestor) {
                $domain = substr($domain, 0, -2);
            }

            if (str_ends_with($domain, '.*')) {
                $base = substr($domain, 0, -2);
                $wildcardBases[($isNegated ? '~' : '').($isAncestor ? '>>' : '')][$base] = true;
            }
        }

        if (!$wildcardBases) {
            return $domains;
        }

        $filtered = array_filter($domains, static function ($d) use ($wildcardBases) {
            $isNegated = str_starts_with($d, '~');
            $isAncestor = str_ends_with($d, '>>');
            $domain = substr($d, $isNegated ? 1 : 0);

            if ($isAncestor) {
                $domain = substr($domain, 0, -2);
            }

            if (str_ends_with($domain, '.*')
                || filter_var($domain, FILTER_VALIDATE_IP) !== false
            ) {
                return true;
            }

            $dotPos = strpos($domain, '.');
            if ($dotPos === false) {
                return true;
            }

            $base = substr($domain, 0, $dotPos);
            $key = ($isNegated ? '~' : '').($isAncestor ? '>>' : '');

            return !isset($wildcardBases[$key][$base]);
        });

        return $filtered;
    }

    /**
     * Remove subdomains covered by an existing parent domain.
     *
     * Example:
     *   example.com + login.example.com -> keep example.com
     *
     * Each domain is checked iteratively by stripping the leftmost label until
     * a parent match is found. Negated domains are never removed.
     *
     * @param array<int, string> $domains
     * @return array<int, string>
     */
    private function removeCoveredSubdomains(array $domains)
    {
        if (!$this->config->flags['remove_subdomains']) {
            return $domains;
        }

        // Build lookup set of candidate parent domains, grouped by negation and suffix.
        $baseSet = [];

        foreach ($domains as $d) {
            $isNegated = str_starts_with($d, '~');
            $domain = substr($d, $isNegated ? 1 : 0);
            $isAncestor = str_ends_with($domain, '>>');

            if ($isAncestor) {
                $domain = substr($domain, 0, -2);
            }

            if (!$isNegated
                && !str_ends_with($domain, '.*')
                && strpos($domain, '.') !== false
            ) {
                $baseSet[$isAncestor ? '>>' : ''][$domain] = true;
            }
        }

        // Include negated parent domains as well.
        foreach ($domains as $d) {
            $isNegated = str_starts_with($d, '~');
            $domain = substr($d, $isNegated ? 1 : 0);
            $isAncestor = str_ends_with($domain, '>>');

            if ($isAncestor) {
                $domain = substr($domain, 0, -2);
            }

            if ($isNegated
                && !str_ends_with($domain, '.*')
                && strpos($domain, '.') !== false
            ) {
                $baseSet['~'.($isAncestor ? '>>' : '')][$domain] = true;
            }
        }

        if (!$baseSet) {
            return $domains;
        }

        $filtered = array_filter($domains, static function ($d) use ($baseSet) {
            $isNegated = str_starts_with($d, '~');
            $domain = substr($d, $isNegated ? 1 : 0);
            $isAncestor = str_ends_with($domain, '>>');

            if ($isAncestor) {
                $domain = substr($domain, 0, -2);
            }

            $key = ($isNegated ? '~' : '').($isAncestor ? '>>' : '');

            // Strip leftmost label iteratively:
            // login.api.example.com -> api.example.com -> example.com
            while (($pos = strpos($domain, '.')) !== false) {
                $domain = substr($domain, $pos + 1);

                if (isset($baseSet[$key][$domain])) {
                    return false;
                }
            }

            return true;
        });

        return $filtered;
    }

    /**
     * Generate a sorting key for a domain entry.
     *
     * @return list<int|string> Sorting tuple
     */
    private function domainSortKey(string $str): array
    {
        $domain = ltrim($str, '~');
        $isTld = preg_match('/^[a-z]+$/', $domain) && !in_array($domain, ['localhost', 'local'], true);
        $isNegated = str_starts_with($str, '~');

        if (!in_array($this->modifier, Registry::DOMAIN_OPTIONS, true) && $this->modifier !== null) {
            return [$isNegated ? 0 : 1, $domain];
        }

        return [
            $isTld ? 0 : ($isNegated ? 1 : 2),
            $domain,
        ];
    }
}
