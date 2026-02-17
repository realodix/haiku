<?php

namespace Realodix\Haiku\Fixer\Classes;

use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Helper;

/**
 * @phpstan-import-type FixerFlags from \Realodix\Haiku\Config\FixerConfig
 */
final class NetworkTidy
{
    /**
     * A list of known options.
     *
     * https://github.com/gorhill/uBlock/blob/2a0842f17/src/js/static-filtering-parser.js#L3132
     *
     * @var list<string>
     */
    const KNOWN_OPTIONS = [
        // must assign values
        'csp', 'denyallow', 'domain', 'from', 'header', 'ipaddress', 'method', 'permissions', 'reason',
        'redirect-rule', 'redirect', 'replace', 'rewrite', 'to', 'urlskip', 'urltransform', 'uritransform',
        // basic
        'all', 'badfilter', 'cname', 'font', 'genericblock', 'image', 'important', 'inline-font',
        'inline-script', 'match-case', 'media', 'other', 'popunder', 'popup', 'script', 'websocket',
        '1p', 'first-party', 'strict1p', 'strict-first-party', '3p', 'third-party', 'strict3p', 'strict-third-party',
        'css', 'stylesheet', 'doc', 'document', 'ehide', 'elemhide', 'frame', 'subdocument', 'ghide', 'generichide',
        'object', 'object-subrequest', 'ping', 'beacon', 'removeparam', 'shide', 'specifichide',
        'xhr', 'xmlhttprequest',
        // deprecated
        'empty', 'mp4', 'queryprune', 'webrtc',
    ];

    /**
     * A list of known options from AdGuard.
     *
     * @var list<string>
     */
    const ADG_KNOWN_OPTIONS = [
        'app', 'content', 'cookie', 'extension', 'hls', 'jsinject', 'jsonprune', 'network', 'path',
        'removeheader', 'referrerpolicy', 'stealth', 'url', 'urlblock', 'xmlprune',
        'client', 'ctag', 'dnstype', 'dnsrewrite', // Adg DNS
    ];

    /**
     * A list of options that can have multiple values.
     *
     * @var array<string, array<mixed>>
     */
    const MULTI_VALUE = [
        'domain' => [], 'from' => [], 'to' => [], 'denyallow' => [], 'method' => [], 'ctag' => [],
        'app' => ['case_sensitive' => true], 'dnstype' => ['case_sensitive' => true],
    ];

    public function __construct(
        private DomainNormalizer $domainNormalizer,
        private NetOptionTransformer $netOptionTransformer,
    ) {}

    /**
     * @param FixerFlags $flags
     */
    public function setFlags(array $flags): void
    {
        $this->domainNormalizer->flags = $flags;
        $this->netOptionTransformer->flags = $flags;
    }

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
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function normalizeOption(string $optionString)
    {
        // Initialize buckets for basic options
        $optionList = [];

        // Initialize buckets for multi-value options
        $multiValueOpts = [];
        foreach (self::MULTI_VALUE as $key => $_config) {
            $multiValueOpts[$key] = [];
        }

        // 1. Split raw option string and classify each option.
        foreach ($this->splitOptions($optionString) as $option) {
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

            $caseSensitive = data_get(self::MULTI_VALUE[$name], 'case_sensitive', false);
            $value = $this->domainNormalizer->applyFix($values[0], '|', $caseSensitive);

            $optionList[] = $name.'='.$value;
        }

        // 3. Transform, Remove duplicates and sort options by priority
        return Helper::uniqueSorted(
            $this->netOptionTransformer->applyFix($optionList),
            fn($v) => $this->optionOrder($v),
        );
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
}
