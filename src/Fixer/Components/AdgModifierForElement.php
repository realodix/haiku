<?php

namespace Realodix\Haiku\Fixer\Components;

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
        private FixerConfig $config,
    ) {}

    public function applyFix(string $str): string
    {
        if (!$this->config->flags['adg_non_basic_rule_modifier']) {
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

        // parse
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
                    $value = Helper::uniqueSortBy($value, fn($d) => ltrim($d, '~'));
                    $value = implode('|', $value);
                }

                $parsed['modifiers'][] = $name.'='.$value;
            }
        }

        $modifiers = collect($parsed['modifiers'])
            ->unique()->sort()->implode(',');

        return '[$'.$modifiers.']';
    }

    public function verify(string $modifier): bool
    {
        return !$this->config->flags['adg_non_basic_rule_modifier']
            || !str_starts_with($modifier, '[$')
            // bracket count mismatch
            || !(substr_count($modifier, '[') != substr_count($modifier, ']'));
    }
}
