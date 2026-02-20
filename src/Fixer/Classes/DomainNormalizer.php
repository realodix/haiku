<?php

namespace Realodix\Haiku\Fixer\Classes;

use Realodix\Haiku\Helper;

final class DomainNormalizer
{
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
        $domains = $this->removeWildcardCoveredDomains($domains);
        $domains = $this->removeSubdomainCoveredDomains($domains);

        return $domains->unique()
            ->sortBy(fn($str) => $this->domainSortPriority($str))
            ->implode($separator);
    }

    /**
     * Determine sorting priority for a domain string.
     *
     * Sorting rules:
     * 1. Localhost-related domains (negated and non-negated) come first.
     * 2. Within each group, negated domains ('~') come first.
     * 3. Then sort alphabetically by the normalized domain value.
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
        $strategy = Helper::flag('domain_order');

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
        if (!Helper::flag('normalize_domains')) {
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
     * Removes leading and trailing slashes and dots from a domain string.
     */
    private function cleanDomain(string $domain): string
    {
        if (!Helper::flag('normalize_domains')) {
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
     * Remove explicit domains covered by wildcard TLD domains.
     *
     * example.com + example.*  → example.*
     *
     * @param \Illuminate\Support\Collection<int, string> $domains
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function removeWildcardCoveredDomains($domains)
    {
        if (!Helper::flag('reduce_wildcard_covered_domains')) {
            return $domains;
        }

        // Collect wildcard bases: example.*
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
                // IP never uses wildcard, but we assume the input is not always correct
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
    private function removeSubdomainCoveredDomains($domains)
    {
        if (!Helper::flag('reduce_subdomains')) {
            return $domains;
        }

        // Collect base domains (non-negated, non-wildcard)
        $baseDomains = $domains
            ->filter(fn($d) => str_contains($d, '.')
                && !str_starts_with($d, '~')
                && !str_ends_with($d, '.*'),
            )->unique();

        if ($baseDomains->isEmpty()) {
            return $domains;
        }

        $baseSet = $baseDomains->flip()->all();

        return $domains->reject(function ($d) use ($baseSet) {
            // don't touch negated domains
            if (str_starts_with($d, '~')) {
                return false;
            }

            // Check each parent domain, starting from the closest one
            // and if parent exists, current domain is redundant.
            //
            // example: login.api.example.com -> api.example.com
            //   -> example.com (found in base set -> covered)
            $parts = explode('.', $d);
            $count = count($parts);
            for ($i = 1; $i < $count; $i++) {
                $parent = implode('.', array_slice($parts, $i));
                if (isset($baseSet[$parent])) {
                    return true;
                }
            }

            return false;
        });
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
