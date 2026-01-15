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
     * A list of options that have case-sensitive values.
     *
     * @var array<string>
     */
    const CASE_SENSITIVE_VALUE = [
        'csp', 'reason', 'removeparam', 'replace', 'urlskip', 'urltransform',
        // AdGuard
        'cookie', 'extension', 'hls', 'jsonprune', 'xmlprune',
        // AdGuard DNS
        'dnsrewrite', 'dnstype',
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
        $filterOptions = $this->parseOptions($m[2]);
        $optionList = $this->normalizeOption($filterOptions);

        return $filterText.'$'.$optionList->implode(',');
    }

    /**
     * Parse the filter options.
     *
     * @return array<string, array<string>>
     */
    private function parseOptions(string $options): array
    {
        // Initialize an empty array
        $parsed = ['genericOpts' => []];
        foreach (self::MULTI_VALUE as $key) {
            $parsed[$key] = [];
        }

        foreach (preg_split(Regex::NET_OPTION_SPLIT, $options) as $option) {
            $parts = explode('=', $option, 2);
            $name = strtolower(ltrim($parts[0], '~'));
            $value = $parts[1] ?? null;

            if (in_array($name, self::MULTI_VALUE)) {
                if ($value !== null) {
                    array_push($parsed[$name], ...[$value]);
                }
            } elseif (in_array($name, self::CASE_SENSITIVE_VALUE, true)) {
                // Lowercase the name, preserve value
                if ($value !== null) {
                    $name .= '='.$value;
                }
                $parsed['genericOpts'][] = $name;
            } else {
                // Lowercase the whole option
                $parsed['genericOpts'][] = strtolower($option);
            }
        }

        return $parsed;
    }

    /**
     * Normalizes and sorts the network filter options.
     *
     * @param array<string, array<string>> $options Parsed options from parseOptions()
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function normalizeOption(array $options)
    {
        $optionList = $options['genericOpts'];

        // Add back the consolidated domain-like options.
        foreach (self::MULTI_VALUE as $name) {
            if (!empty($options[$name])) {
                $value = Helper::normalizeDomain($options[$name][0], '|');
                $optionList[] = $name.'='.$value;
            }
        }

        $processedOptions = [];
        foreach ($optionList as $option) {
            $transformed = $this->applyOption($option);
            if ($transformed) {
                $processedOptions[] = $transformed;
            }
        }

        return Helper::uniqueSorted($processedOptions, fn($v) => $this->optionOrder($v));
    }

    /**
     * Applies a set of dynamic rules to transform or remove a filter option.
     */
    private function applyOption(string $option): string
    {
        // https://github.com/gorhill/uBlock/wiki/Static-filter-syntax#_-aka-noop
        // https://adguard.com/kb/general/ad-filtering/create-own-filters/#noop-modifier
        if (str_starts_with($option, '_')) {
            return '';
        }

        // https://github.com/gorhill/uBlock/wiki/Static-filter-syntax#empty
        // https://adguard.com/kb/general/ad-filtering/create-own-filters/#empty-modifier
        if ($option === 'empty') {
            return 'redirect=nooptext';
        }

        // https://github.com/gorhill/uBlock/wiki/Static-filter-syntax#mp4
        // https://adguard.com/kb/general/ad-filtering/create-own-filters/#mp4-modifier
        if ($option === 'mp4') {
            return 'media,redirect=noopmp4-1s';
        }

        return $option;
    }

    /**
     * Returns a string representing the order of the filter option.
     */
    private function optionOrder(string $option): string
    {
        // Prio 1: (Highest): 'important' and 'party' options must always be at the top.
        if ($option === 'important' || $option === 'badfilter') {
            return '0'.$option;
        }
        if ($option === 'strict1p' || $option === 'strict-first-party'
            || $option === 'strict3p' || $option === 'strict-third-party') {
            return '1'.$option;
        }
        if (preg_match('/^~?((?:1|3)p|(first|third)-party)/', $option)) {
            return '2'.ltrim($option, '~');
        }

        // Prio 3
        if (preg_match('/^(csp|header|method|permissions|redirect(?:-rule)?
                |removeparam|replace|urlskip|urltransform
            )=/x',
            $option)) {
            return '4'.$option;
        }

        if (str_starts_with($option, 'denyallow=') || str_starts_with($option, 'domain=')
            || str_starts_with($option, 'from=') || str_starts_with($option, 'to=')
            || str_starts_with($option, 'ipaddress=')) {
            return '5'.$option;
        }

        // Always put at the end
        if (str_starts_with($option, 'reason=')) {
            return $option;
        }

        // Prio 2: Other options
        return '3'.$option;
    }
}
