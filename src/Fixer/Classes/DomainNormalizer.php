<?php

namespace Realodix\Haiku\Fixer\Classes;

final class DomainNormalizer
{
    public bool $xMode;

    public function applyFix(string $domainStr, string $separator): string
    {
        // regex domain, don't touch
        if ($this->containsRegexDomain($domainStr)) {
            return $domainStr;
        }

        $domains = collect(explode($separator, $domainStr))
            ->filter(fn($d) => $d !== '')
            ->map(function ($str) {
                $domain = strtolower($str);

                return $this->cleanDomain($domain);
            });

        // Domain Coverage Reducer
        $domains = $this->removeWildcardCoveredDomains($domains);
        $domains = $this->removeSubdomainCoveredDomains($domains);

        return $domains->unique()->sortBy(function ($str) {
            // ensure negated domains ('~') come first
            if (str_starts_with($str, '~')) {
                return '/'.$str;
            }

            return $str;
        })->implode($separator);
    }

    private function cleanDomain(string $domain): string
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
    private function removeWildcardCoveredDomains($domains)
    {
        if ($this->xMode === false) {
            return $domains;
        }

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
        if ($this->xMode === false) {
            return $domains;
        }

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

    private function containsRegexDomain(string $domainStr): bool
    {
        return str_starts_with($domainStr, '/') && str_ends_with($domainStr, '/')
            || str_contains($domainStr, '/') && preg_match('/[\\^([{$\\\]/', $domainStr);
    }
}
