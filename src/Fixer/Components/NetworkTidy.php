<?php

namespace Realodix\Haiku\Fixer\Components;

use Realodix\Haiku\Config\FixerConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Support\Arr;
use Realodix\Haiku\Support\Util;

final class NetworkTidy
{
    /**
     * A list of options that can have multiple values.
     */
    const MULTI_VALUE = [
        'domain' => [], 'from' => [], 'to' => [], 'top' => [], 'denyallow' => [], 'method' => [], 'ctag' => [],
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
            return $line;
        }

        $fPattern = $m[1];
        $fOptions = $this->normalizeOption($m[2]);

        return $fPattern.'$'.implode(',', $fOptions);
    }

    /**
     * Normalizes and sorts the network filter options.
     *
     * @param string $optionString Parsed options
     * @return array<int, string>
     */
    private function normalizeOption(string $optionString)
    {
        /** @var list<array{name: string, values: list<string>}> */
        $nodes = [];

        // 1. Parse raw option string into structured nodes
        $multiIndexes = []; // map: option name -> index of its node in $nodes
        foreach (Util::splitOptions($optionString) as $option) {
            $parts = explode('=', $option, 2);
            $name = strtolower($parts[0]);
            $value = $parts[1] ?? null;

            // Option: multi value
            if (isset(self::MULTI_VALUE[$name]) && $value !== null) {
                // First occurrence defines the position of the aggregated node
                if (!isset($multiIndexes[$name])) {
                    // Record the next node's index before appending it.
                    $multiIndexes[$name] = count($nodes);
                    $nodes[] = ['name' => $name, 'values' => []];
                }

                // Subsequent occurrences only contribute values
                $nodes[$multiIndexes[$name]]['values'][] = $value;

                continue;
            }

            if ($value !== null) {
                // Option: single value
                $nodes[] = ['name' => $name, 'values' => [$value]];
            } else {
                // Option: non value
                $nodes[] = ['name' => $name, 'values' => []];
            }
        }

        // 2. Convert nodes back into string
        $optionList = [];
        /** @var list<array{name: string, values: list<string>}> $nodes */
        foreach ($nodes as $node) {
            $name = $node['name'];
            $values = $node['values'];

            // Option: multi value
            if (isset(self::MULTI_VALUE[$name]) && $values !== []) {
                $caseSensitive = self::MULTI_VALUE[$name]['case_sensitive'] ?? false;
                $this->domainNormalizer->modifier = $name;
                $value = $this->domainNormalizer->applyFix(implode('|', $values), '|', $caseSensitive);
                $optionList[] = $name.'='.$value;

                continue;
            }

            // Option: single value
            if (count($values) === 1) {
                $optionList[] = $name.'='.$values[0];

                continue;
            }

            // Option: non value
            $optionList[] = $name;
        }

        // 3. Post-processing
        $optionList = $this->netOptionTransformer->applyFix($optionList);
        $optionList = array_filter($optionList, static fn($s) => $s !== '');
        $optionList = array_unique($optionList);

        if (!$this->config->flags['option_order']) {
            return $optionList;
        }

        return Arr::sortBy($optionList, fn(string $v) => $this->optionOrder($v));
    }

    /**
     * Returns a string representing the order of the filter option.
     */
    private function optionOrder(string $option): string
    {
        $option = ltrim($option, '~');

        if ($this->config->flags['option_order'] === 'name') {
            return $option;
        }

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
                || str_starts_with($option, 'from=') || str_starts_with($option, 'to=') || str_starts_with($option, 'top=')
                || str_starts_with($option, 'ipaddress=')) {
                return '5'.$option;
            }

            return '4'.$option;
        }

        // P2: basic options
        return '3'.$option;
    }
}
