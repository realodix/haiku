<?php

namespace Realodix\Haiku\Fixer\Classes;

use Realodix\Haiku\Config\FixerConfig;

final class DomainNormalizer
{
    public function __construct(
        private FixerConfig $config,
    ) {}

    public function applyFix(string $domainStr, string $separator, bool $caseSensitive = false): string
    {
        // Regex domain, don't touch
        if ($this->containsRegexDomain($domainStr)) {
            return $domainStr;
        }

        $domainStr = $this->fixWrongSeparator($domainStr, $separator);

        $domains = collect(explode($separator, $domainStr))
            ->filter(fn($d) => $d !== '')
            ->map(function ($domainStr) use ($caseSensitive) {
                if ($caseSensitive === false) {
                    $domainStr = strtolower($domainStr);
                }

                return $this->cleanDomain($domainStr);
            });

        // Domain coverage reducer
        $domains = $domains->values()->all();
        $domains = $this->removeWildcardCoveredDomains($domains);
        $domains = $this->removeSubdomainCoveredDomains($domains);

        return collect($domains)->unique()
            ->sortBy(fn($str) => $this->domainSortPriority($str))
            ->implode($separator);
    }

    /**
     * Determine sorting priority for a domain string.
     *
     * @return list<int|string>
     */
    private function domainSortPriority(string $str): array
    {
        $isNegated = str_starts_with($str, '~');
        $domain = ltrim($str, '~');
        $localhostDomains = [
            'localhost', 'local',
            '127.0.0.1', '0.0.0.0',
            '[::1]', '[::]',
        ];
        $isLocalhost = in_array($domain, $localhostDomains, true);
        $strategy = $this->config->getFlag('domain_order');

        return match ($strategy) {
            'negated_first' => [
                $isNegated ? 0 : 1,
                $domain,
            ],

            'localhost_first' => [
                $isLocalhost ? 0 : 1,
                $domain,
            ],

            'localhost_negated_first' => [
                $isLocalhost ? 0 : 1,
                $isNegated ? 0 : 1,
                $domain,
            ],

            default => [$domain],
        };
    }

    /**
     * Fixes incorrect separators in domain strings.
     *
     * If the domain string contains an incorrect separator (e.g. '|' instead of ','),
     * replace it with the correct separator.
     *
     * @param string $domainStr The domain string to fix
     * @param string $separator The correct separator to use
     * @return string The fixed domain string
     */
    private function fixWrongSeparator(string $domainStr, string $separator): string
    {
        if (!$this->config->getFlag('normalize_domains')) {
            return $domainStr;
        }

        // @phpstan-ignore match.unhandled
        $typo = match ($separator) {
            '|' => ',',
            ',' => '|',
        };

        if (str_contains($domainStr, $typo)) {
            $domainStr = str_replace($typo, $separator, $domainStr);
        }

        return $domainStr;
    }

    /**
     * Normalizes a domain string by trimming whitespace and removing leading "/"
     * or "." and trailing "/".
     */
    private function cleanDomain(string $domain): string
    {
        if (!$this->config->getFlag('normalize_domains')) {
            return $domain;
        }

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
     * Remove domains covered by wildcard TLD domains
     *
     * example.* + example.com -> keep example.*
     *
     * @param array<int, string> $domains
     * @return array<int, string>
     */
    private function removeWildcardCoveredDomains($domains)
    {
        if (!$this->config->getFlag('reduce_wildcard_covered_domains')) {
            return $domains;
        }

        // Build lookup set of wildcard prefixes (example.* -> example)
        // Used to detect domains covered by wildcard TLD rules
        $wildcardBases = [];
        foreach ($domains as $d) {
            if ($d[0] !== '~' && str_ends_with($d, '.*')) {
                $wildcardBases[substr($d, 0, -2)] = true;
            }
        }

        if (!$wildcardBases) {
            return $domains;
        }

        $filtered = array_filter($domains, static function ($d) use ($wildcardBases) {
            if (str_starts_with($d, '~') // keep negated domains
                || str_ends_with($d, '.*') // keep wildcard domains
                // IP never uses wildcard, but we assume the input is not always correct
                || filter_var($d, FILTER_VALIDATE_IP) !== false
            ) {
                return true;
            }

            // Extract first label prefix (example.com -> example)
            $dotPos = strpos($d, '.');
            if ($dotPos === false) {
                return true;
            }
            $base = substr($d, 0, $dotPos);

            // Reject if covered by wildcard
            return !isset($wildcardBases[$base]);
        });

        return $filtered;
    }

    /**
     * Remove subdomains covered by their base domain.
     *
     * example.com + login.example.com -> example.com
     *
     * @param array<int, string> $domains
     * @return array<int, string>
     */
    private function removeSubdomainCoveredDomains($domains)
    {
        if (!$this->config->getFlag('reduce_subdomains')) {
            return $domains;
        }

        // Build lookup set of candidate parent domains. Used to detect subdomains
        // covered by their parent.
        $baseSet = [];

        foreach ($domains as $d) {
            if ($d[0] !== '~'
                && !str_ends_with($d, '.*')
                && strpos($d, '.') !== false
            ) {
                $baseSet[$d] = true;
            }
        }

        if (!$baseSet) {
            return $domains;
        }

        $filtered = array_filter($domains, static function ($d) use ($baseSet) {
            // Keep negated domains
            if ($d[0] === '~') {
                return true;
            }

            $check = $d;

            // Strip leftmost label iteratively:
            // login.api.example.com -> api.example.com -> example.com
            while (($pos = strpos($check, '.')) !== false) {
                $check = substr($check, $pos + 1);

                if (isset($baseSet[$check])) {
                    return false; // Covered
                }
            }

            return true;
        });

        return $filtered;
    }

    /**
     * Determines if a given domain string contains a regex domain.
     *
     * @param string $domainStr The domain string to check
     * @return bool True if the domain string contains a regex domain, false otherwise
     */
    private function containsRegexDomain(string $domainStr): bool
    {
        return str_starts_with($domainStr, '/') && str_ends_with($domainStr, '/')
            || str_contains($domainStr, '/') && preg_match('/[\\^([{$\\\]/', $domainStr);
    }
}
