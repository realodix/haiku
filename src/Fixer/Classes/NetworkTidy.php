<?php

namespace Realodix\Haiku\Fixer\Classes;

use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Helper;

final class NetworkTidy
{
    /**
     * A list of options that can have multiple values.
     *
     * @var array<string>
     */
    const MULTI_VALUE = [
        'domain', 'from', 'to', 'denyallow', 'method',
    ];

    /**
     * Tidies a network filter rule by normalizing options and sorting domains.
     */
    public function applyFix(string $line): string
    {
        if (!preg_match(Regex::NET_OPTION, $line, $m)) {
            return $line;
        }

        $filterText = $m[1];
        $optionList = $this->normalizeOption($m[2]);

        return $filterText.'$'.$optionList->implode(',');
    }

    /**
     * Normalizes and sorts the network filter options.
     *
     * @param string $optionString Parsed options from parseOptions()
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function normalizeOption(string $optionString)
    {
        // Initialize buckets for basic options
        $optionList = [];

        // Initialize buckets for multi-value options
        $multiValueOpts = [];
        foreach (self::MULTI_VALUE as $key) {
            $multiValueOpts[$key] = [];
        }

        // 1. Split raw option string and classify each option.
        foreach (preg_split(Regex::NET_OPTION_SPLIT, $optionString) as $option) {
            $parts = explode('=', $option, 2);
            $name = strtolower($parts[0]);
            $value = $parts[1] ?? null;

            // if option supports multiple values, collect them
            if (isset($multiValueOpts[$name]) && $value !== null) {
                $multiValueOpts[$name][] = $value;

                continue;
            }

            // otherwise treat it as a basic option
            if ($value !== null) {
                $name .= '='.$value;
            }

            $optionList[] = $name;
        }

        // 2. Rebuild consolidated multi-value options (domain=, from=, etc.)
        foreach ($multiValueOpts as $name => $values) {
            if ($values === []) {
                continue;
            }

            $optionList[] = $name.'='.Helper::normalizeDomain($values[0], '|');
        }

        // 3. Transform, Remove duplicates and sort options by priority
        return Helper::uniqueSorted(
            $this->applyOption($optionList),
            fn($v) => $this->optionOrder($v),
        );
    }

    /**
     * Applies a set of dynamic rules to transform or remove a filter option.
     *
     * @param array<string> $options
     * @return array<string>
     */
    private function applyOption(array $options): array
    {
        $result = [];
        foreach ($options as $option) {
            // "_" is a noop modifier → drop entirely
            if (str_starts_with($option, '_')) {
                continue;
            }

            // https://github.com/gorhill/uBlock/wiki/Static-filter-syntax#empty
            // https://adguard.com/kb/general/ad-filtering/create-own-filters/#empty-modifier
            if ($option === 'empty') {
                $result[] = 'redirect=nooptext';

                continue;
            }

            // https://github.com/gorhill/uBlock/wiki/Static-filter-syntax#mp4
            // https://adguard.com/kb/general/ad-filtering/create-own-filters/#mp4-modifier
            if ($option === 'mp4') {
                $result[] = 'media,redirect=noopmp4-1s';

                continue;
            }

            // Default: keep option as-is
            $result[] = $option;
        }

        return $result;
    }

    /**
     * Returns a string representing the order of the filter option.
     */
    private function optionOrder(string $option): string
    {
        $option = ltrim($option, '~');

        // P1: 'important' and 'party' options should always be at the front/beginning
        if ($option === 'important' || $option === 'badfilter' || $option === 'match-case') {
            return '0'.$option;
        }
        if ($option === 'strict1p' || $option === 'strict-first-party'
            || $option === 'strict3p' || $option === 'strict-third-party') {
            return '1'.$option;
        }
        if ($option === '1p' || $option === 'first-party'
            || $option === '3p' || $option === 'third-party') {
            return '2'.$option;
        }

        // Always put at the end
        if (str_starts_with($option, 'reason=')) {
            return $option;
        }

        // P3: options that support values
        if (str_starts_with($option, 'denyallow=') || str_starts_with($option, 'domain=')
            || str_starts_with($option, 'from=') || str_starts_with($option, 'to=')
            || str_starts_with($option, 'ipaddress=')) {
            return '5'.$option;
        }
        if (str_contains($option, '=')) {
            return '4'.$option;
        }

        // P2: basic options
        return '3'.$option;
    }
}
