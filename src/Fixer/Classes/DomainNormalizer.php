<?php

namespace Realodix\Haiku\Fixer\Classes;

final class DomainNormalizer
{
    public function applyFix(string $domain, string $separator): string
    {
        // regex domain, don't touch
        if (str_starts_with($domain, '/') && str_ends_with($domain, '/')) {
            return $domain;
        }

        $domain = explode($separator, $domain);
        $domain = collect($domain)
            ->filter(fn($d) => $d !== '')
            ->map(function ($str) {
                $domain = strtolower($str);
                $domain = $this->cleanDomain($domain);

                return $domain;
            })->unique()
            ->sortBy(function ($str) {
                // ensure negated domains ('~') come first
                if (str_starts_with($str, '~')) {
                    return '/'.$str;
                }

                return $str;
            })->implode($separator);

        return $domain;
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
}
