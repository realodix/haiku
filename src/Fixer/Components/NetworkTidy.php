<?php

namespace Realodix\Haiku\Fixer\Components;

use Realodix\Haiku\Config\FixerConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Helper;

final class NetworkTidy
{
    /**
     * A list of known options.
     *
     * https://github.com/gorhill/uBlock/blob/2a0842f17/src/js/static-filtering-parser.js#L3132
     */
    const KNOWN_OPTIONS = [
        // must assign values
        'csp', 'denyallow', 'domain', 'from', 'header', 'ipaddress', 'method', 'permissions', 'reason',
        'redirect-rule', 'redirect', 'replace', 'rewrite', 'to', 'urlskip', 'urltransform', 'uritransform',
        // basic
        'all', 'badfilter', 'cname', 'font', 'genericblock', 'image', 'important', 'inline-font', 'inline-script',
        'match-case', 'media', 'other', 'popunder', 'popup', 'script', 'websocket',
        '1p', 'first-party', 'strict1p', 'strict-first-party', '3p', 'third-party', 'strict3p', 'strict-third-party',
        'css', 'stylesheet', 'doc', 'document', 'ehide', 'elemhide', 'frame', 'subdocument', 'ghide', 'generichide',
        'object', 'ping', 'beacon', 'removeparam', 'shide', 'specifichide',
        'xhr', 'xmlhttprequest',
        // deprecated
        'empty', 'mp4', 'object-subrequest', 'queryprune', 'webrtc',
    ];

    /**
     * A list of known options from AdGuard.
     */
    const ADG_KNOWN_OPTIONS = [
        'app', 'content', 'cookie', 'extension', 'hls', 'jsinject', 'jsonprune', 'network', 'path',
        'removeheader', 'referrerpolicy', 'stealth', 'url', 'urlblock', 'xmlprune',
        'client', 'ctag', 'dnstype', 'dnsrewrite', // Adg DNS
    ];

    /**
     * A list of options that can have multiple values.
     */
    const MULTI_VALUE = [
        'domain' => [], 'from' => [], 'to' => [], 'denyallow' => [], 'method' => [], 'ctag' => [],
        'app' => ['case_sensitive' => true], 'dnstype' => ['case_sensitive' => true],
    ];

    public function __construct(
        private DomainNormalizer $domainNormalizer,
        private NetOptionTransformer $netOptionTransformer,
        private FixerConfig $config,
    ) {}

    /**
     * Tidies a network filter rule by normalizing options and sorting domains.
     */
    public function applyFix(string $line): string
    {
        if (!preg_match(Regex::NET_OPTION, $line, $m)) {
            return $this->removeUnnecessaryWildcard($line);
        }

        $filterText = $this->removeUnnecessaryWildcard($m[1]);
        $optionList = $this->normalizeOption($m[2]);

        return $filterText.'$'.implode(',', $optionList);
    }

    /**
     * Splits a network filter's options.
     *
     * @param string $optionString Raw option string
     * @return list<string>
     */
    public function splitOptions(string $optionString): array
    {
        $knownOptions = array_merge(self::KNOWN_OPTIONS, self::ADG_KNOWN_OPTIONS, [',']);
        $pattern = '/,(?=(?:\s|~)?('.implode('|', $knownOptions).')\b|$)/i';

        return preg_split($pattern, $optionString);
    }

    /**
     * Normalizes and sorts the network filter options.
     *
     * @param string $optionString Parsed options from parseOptions()
     * @return array<int, string>
     */
    private function normalizeOption(string $optionString)
    {
        $nodes = [];
        // $multiRefs keeps references to multi-value nodes inside $nodes.
        // This allows us to append values without searching the array again.
        $multiRefs = [];

        // 1. Parse raw option string into structured nodes
        foreach ($this->splitOptions($optionString) as $option) {
            $parts = explode('=', $option, 2);
            $name = strtolower($parts[0]);
            $value = $parts[1] ?? null;

            // Handle multi-value options (e.g. domain=, from=, etc.)
            if (isset(self::MULTI_VALUE[$name]) && $value !== null) {
                if (!isset($multiRefs[$name])) {
                    $nodes[] = ['name' => $name, 'values' => []];
                    // Store reference to the just-created node for O(1) updates
                    $multiRefs[$name] = &$nodes[array_key_last($nodes)];
                }

                // Append value to the existing multi-value node
                $multiRefs[$name]['values'][] = $value;

                continue;
            }

            // Otherwise, treat as a single-value option
            $nodes[] = ['name' => $name, 'value' => $value];
        }

        // 2. Convert nodes back into normalized option strings
        $optionList = [];
        foreach ($nodes as $node) {
            // multi-value
            if (isset($node['values'])) {
                $name = $node['name'];
                $values = $node['values'];
                // Some multi-value options may require case-sensitive handling
                $caseSensitive = self::MULTI_VALUE[$name]['case_sensitive'] ?? false;

                $value = $this->domainNormalizer->applyFix(implode('|', $values), '|', $caseSensitive);

                $optionList[] = $name.'='.$value;

                continue;
            }

            // basic
            if ($node['value'] !== null) {
                $optionList[] = $node['name'].'='.$node['value'];
            } else {
                $optionList[] = $node['name'];
            }
        }

        // 3. Apply additional transformations (normalization rules, aliases, etc.)
        $optionList = $this->netOptionTransformer->applyFix($optionList);

        // 4. Remove duplicates and sort based on defined priority
        return Helper::uniqueSortBy($optionList, fn($v) => $this->optionOrder($v));
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
        if (str_contains($option, '=')) {
            if (str_starts_with($option, 'denyallow=') || str_starts_with($option, 'domain=')
                || str_starts_with($option, 'from=') || str_starts_with($option, 'to=')
                || str_starts_with($option, 'ipaddress=')) {
                return '5'.$option;
            }

            return '4'.$option;
        }

        // P2: basic options
        return '3'.$option;
    }

    /**
     * Removes unnecessary wildcard characters ('*') from a filter rule.
     *
     * @param string $line The filter text.
     * @return string string The filter text with unnecessary wildcards removed.
     */
    private function removeUnnecessaryWildcard(string $line): string
    {
        if (!$this->config->flags['remove_unnecessary_wildcard']
            || !str_contains($line, '*')
            || preg_match('/^@?\*:\/\//', $line) // uBlacklist rule
        ) {
            return $line;
        }

        $hadStar = false;
        $allowList = false;

        if (str_starts_with($line, '@@')) {
            $allowList = true;
            $line = substr($line, 2);
        }

        // Trim leading '*', keep 1 char
        // Example: "*example.com" -> "example.com"
        while (strlen($line) > 1 && $line[0] === '*') {
            $afterChar = $line[1];
            if ($allowList // unless is part of an allowlist
                // unless followed by the anchor '$' or '#'
                || $afterChar === '$' || $afterChar === '#'
            ) {
                break;
            }

            $line = substr($line, 1);
            $hadStar = true;
        }

        // Trim trailing '*', keep 1 char
        // Example: "example.com*" -> "example.com"
        while (strlen($line) > 1 && $line[strlen($line) - 1] === '*') {
            $prevChar = $line[strlen($line) - 2];
            // unless preceded by the anchor '|'
            if ($prevChar === '|') {
                break;
            }

            $line = substr($line, 0, -1);
            $hadStar = true;
        }

        // If wildcards were removed and result is regex pattern /.../, append '*'
        if ($hadStar && str_starts_with($line, '/') && str_ends_with($line, '/')) {
            $line .= '*';
        }

        if ($allowList) {
            $line = '@@'.$line;
        }

        return $line;
    }
}
