<?php

namespace Realodix\Haiku\Fixer\Classes;

use Realodix\Haiku\Config\FixerConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Helper;

/**
 * https://adguard.com/kb/general/ad-filtering/create-own-filters/#non-basic-rules-modifiers
 */
final class AdgModifierForElement
{
    public function __construct(
        private NetworkTidy $networkTidy,
    ) {}

    public function applyFix(string $str): string
    {
        if (!FixerConfig::getFlag('adg_non_basic_rules_modifiers')) {
            return $str;
        }

        // unwrap  `[` and `]`
        $str = substr($str, 1, -1);

        if (!preg_match(Regex::NET_OPTION, $str, $m)) {
            return $str;
        }

        $modifiers = $m[2];

        // initialize an empty array
        $parsed = ['modifiers' => []];
        $multiValue = ['app', 'domain'];
        foreach ($multiValue as $key) {
            $parsed[$key] = [];
        }

        foreach ($this->networkTidy->splitOptions($modifiers) as $option) {
            $parts = explode('=', $option, 2);
            $name = ltrim($parts[0], '~');
            $value = $parts[1] ?? null;

            if (in_array($name, $multiValue)) {
                if ($value !== null) {
                    array_push($parsed[$name], ...[$value]);
                }
            } else {
                $parsed['modifiers'][] = $option;
            }
        }

        // add back the consolidated domain-like options.
        foreach ($multiValue as $name) {
            if (!empty($parsed[$name])) {
                $domainString = $parsed[$name][0];

                if (str_contains($domainString, '/')) {
                    $value = $domainString;
                } else {
                    $value = explode('|', $domainString);
                    $value = Helper::uniqueSorted($value, fn($d) => ltrim($d, '~'))
                        ->implode('|');
                }

                $parsed['modifiers'][] = $name.'='.$value;
            }
        }

        $modifiers = collect($parsed['modifiers'])
            ->unique()->sort()->implode(',');

        return '[$'.$modifiers.']';
    }

    /**
     * Handle complicated AdGuard modifier and re-parse rule.
     *
     * @return array{0:string,1:string,2:string,3:string}|array{}
     */
    public function resolveComplicated(string $line, string $domainBlock, string $modifier): array
    {
        if ((!FixerConfig::getFlag('adg_non_basic_rules_modifiers'))
            || !str_starts_with($modifier, '[$') || !$this->isComplicated($modifier)
        ) {
            return [];
        }

        $modifier = $this->extract($domainBlock);

        if ($modifier === null) {
            return [];
        }

        $line = substr($line, strlen($modifier));

        if (!preg_match(Regex::COSMETIC_RULE, $line, $m)) {
            return [];
        }

        return [$modifier, $m[3], $m[4], $m[5]];
    }

    /**
     * Extract AdGuard modifier using backward scan.
     */
    private function extract(string $str): ?string
    {
        $len = strlen($str);
        $open = null; // '/'

        for ($i = $len - 1; $i >= 0; $i--) {
            $c = $str[$i];

            // ===== REGEX =====
            if ($c === '/') {
                if ($open === $c) {
                    $open = null;
                } elseif ($open === null) {
                    $open = $c;
                }

                continue;
            }

            // ===== CLOSING BRACKET =====
            if ($open === null && $c === ']') {
                // IPv6 literal? -> skip
                $ipv6Start = $this->isIpv6Literal($str, $i);
                if ($ipv6Start !== null) {
                    $i = $ipv6Start;

                    continue;
                }

                // this is modifier end
                return substr($str, 0, $i + 1);
            }
        }

        return null;
    }

    /**
     * Checks if a given AdGuard modifier is complicated. i.e., contains regex string
     * or Regex::COSMETIC_RULE fails to extract.
     */
    private function isComplicated(string $modifier): bool
    {
        return
            // contains regex
            substr_count($modifier, '/]') > 0
            // bracket count mismatch
            || substr_count($modifier, '[') != substr_count($modifier, ']');
    }

    private function isIpv6Literal(string $str, int $end): ?int
    {
        for ($i = $end - 1; $i >= 0; $i--) {
            $c = $str[$i];

            if ($c === '[') {
                $inside = substr($str, $i + 1, $end - $i - 1);

                // only hex digits and colon
                if ($inside !== '' && preg_match('/^[0-9a-fA-F:]+$/', $inside)) {
                    return $i; // opening bracket position
                }

                return null;
            }

            if (!ctype_xdigit($c) && $c !== ':') {
                return null;
            }
        }

        return null;
    }
}
