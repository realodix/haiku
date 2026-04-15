<?php

namespace Realodix\Haiku\Linter\Rules\Redundant;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Linter\Util;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 *
 * @phpstan-type _ParsedAttrSelector array{
 *     tag: string,
 *     attr: string,
 *     operator: string,
 *     value: string,
 *     modifier: string
 * }
 * @phpstan-type _SeenAttrSelector array{
 *     data: _ParsedAttrSelector,
 *     line: int
 * }
 */
final class CosmeticCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    /**
     * @param list<string> $content
     * @return list<_RuleError>
     */
    public function check(array $content): array
    {
        if (!$this->config->rules['no_dupe_rules']) {
            return [];
        }

        /** @var list<_RuleError> */
        $errors = [];

        /** @var array<string, int> */
        $exactSeen = [];

        /** @var array<string, int> */
        $genericCosm = []; // Separator + Selector -> LineNum

        /** @var array<string, array<string, int>> */
        $domainSeen = [];  // Separator + Selector -> [Lowercase Domain -> LineNum]

        /** @var array<string, list<_SeenAttrSelector>> */
        $selectorSeen = [];

        foreach ($content as $index => $line) {
            $line = trim($line);
            $lineNum = $index + 1;

            if (Util::isCommentOrEmpty($line) || str_starts_with($line, '[$')) {
                continue;
            }

            if (!preg_match(Regex::IS_COSMETIC_RULE, $line)) {
                continue;
            }

            // 1. Exact duplicate detection (case-sensitive)
            if (isset($exactSeen[$line])) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    "Redundant filter: '%s' already defined on line %d.",
                    $line,
                    $exactSeen[$line],
                ))->line($lineNum)->build();

                continue;
            }
            $exactSeen[$line] = $lineNum;

            if (!preg_match(Regex::COSMETIC_RULE, $line, $m)) {
                continue;
            }

            $domainStr = trim($m[3]);
            $separator = $m[4];
            $selector = $m[5];
            $sepSelector = $separator.$selector; // e.g. "##.ads"

            $currDomains = $this->parseDomains($domainStr);

            // 2. Global vs domain redundancy
            if ($domainStr === '') {
                $genericCosm[$sepSelector] = $lineNum;
            } else {
                if (isset($genericCosm[$sepSelector])) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        "Redundant filter: '%s' already covered by '%s' on line %d.",
                        $line,
                        $sepSelector,
                        $genericCosm[$sepSelector],
                    ))->line($lineNum)->build();
                } else {
                    foreach ($currDomains as $d => $_) {
                        if (isset($domainSeen[$sepSelector][$d])) {
                            $errors[] = RuleErrorBuilder::message(sprintf(
                                "Redundant filter: domain '%s' already covered on line %d.",
                                $d,
                                $domainSeen[$sepSelector][$d],
                            ))->line($lineNum)->build();
                        } else {
                            $domainSeen[$sepSelector][$d] = $lineNum;
                        }
                    }
                }
            }

            // 3. Attribute selector dominance check
            // Detects redundancy based on semantic coverage between attribute selectors.
            // This is NOT a full CSS selector analysis.
            $parsed = $this->parseAttributeSelector($selector);

            if ($parsed !== null) {
                // Group by separator, tag, and attribute to compare semantic coverage
                $attrKey = $separator.'|'.$parsed['tag'].'|'.$parsed['attr'];
                $list = $selectorSeen[$attrKey] ?? [];

                foreach ($list as $prev) {
                    $prevDomains = $prev['domains'];

                    // Case: previous covered by current (THIS IS YOUR CASE)
                    if ($this->isAttrCoveredBy($prev['data'], $parsed)) {
                        // GLOBAL current → previous fully redundant
                        if ($currDomains === []) {
                            $errors[] = RuleErrorBuilder::message(sprintf(
                                "Redundant filter: '%s' is redundant due to more general selector on line %d.",
                                $content[$prev['line'] - 1],
                                $lineNum,
                            ))->line($prev['line'])->build();

                            continue;
                        }

                        // Partial domain check
                        foreach ($prevDomains as $d => $_) {
                            if (isset($currDomains[$d])) {
                                $errors[] = RuleErrorBuilder::message(sprintf(
                                    "Redundant filter: domain '%s' in '%s' already covered on line %d.",
                                    $d,
                                    // $content[$prev['line'] - 1],
                                    $d.$separator.$prev['selector'],
                                    $lineNum,
                                ))->line($prev['line'])->build();
                            }
                        }
                    }

                    // Case: current covered by previous
                    if ($this->isAttrCoveredBy($parsed, $prev['data'])) {
                        if ($prevDomains === []) {
                            $errors[] = RuleErrorBuilder::message(sprintf(
                                "Redundant filter: '%s' already covered by a previous selector on line %d.",
                                $line,
                                $prev['line'],
                            ))->line($lineNum)->build();

                            continue 2;
                        }

                        foreach ($currDomains as $d => $_) {
                            if (isset($prevDomains[$d])) {
                                $errors[] = RuleErrorBuilder::message(sprintf(
                                    "Redundant filter: domain '%s' in '%s' already covered on line %d.",
                                    $d,
                                    $d.$separator.$selector,
                                    $prev['line'],
                                ))->line($lineNum)->build();

                                continue 2;
                            }
                        }
                    }
                }

                $selectorSeen[$attrKey][] = [
                    'data' => $parsed,
                    'line' => $lineNum,
                    'domains' => $currDomains,
                    'selector' => $selector,
                ];
            }
        }

        return $errors;
    }

    /**
     * Parse comma-separated domains into a normalized set.
     *
     * @param string $domainStr Comma-separated domain string
     * @return array<string, bool> Associative array with domain as key and true as value
     */
    private function parseDomains(string $domainStr): array
    {
        if ($domainStr === '') {
            return [];
        }

        $result = [];
        foreach (explode(',', $domainStr) as $d) {
            $result[strtolower(trim($d))] = true;
        }

        return $result;
    }

    /**
     * Parse a simple attribute selector.
     *
     * Supports only selectors in the form:
     *   tag[attr op "value" i?]
     *
     * @return _ParsedAttrSelector|null
     */
    private function parseAttributeSelector(string $selector): ?array
    {
        if (!preg_match(
            '/^(?:(?<tag>[a-z0-9_-]+))?\[(?<attr>[a-z0-9_-]+)\s*(?<op>\^=|\$=|\*=|=)\s*"(?<val>[^"]+)"\s*(?<mod>i)?\]$/i',
            $selector,
            $m,
        )) {
            return null;
        }

        return [
            'tag' => strtolower($m['tag']),
            'attr' => strtolower($m['attr']),
            'operator' => $m['op'],
            'value' => $m['val'],
            'modifier' => strtolower($m['mod'] ?? ''),
        ];
    }

    /**
     * Determine whether attribute selector A is semantically covered by selector B.
     *
     * A is considered covered by B if every element matched by A
     * would also be matched by B.
     *
     * This only applies to simple attribute selectors with the same
     * tag and attribute name.
     *
     * Examples:
     *   [href="abc"]    is covered by [href*="a"]
     *   [href^="https"] is covered by [href*="http"]
     *
     * @param _ParsedAttrSelector $a
     * @param _ParsedAttrSelector $b
     */
    private function isAttrCoveredBy(array $a, array $b): bool
    {
        if ($a['attr'] !== $b['attr'] || $a['tag'] !== $b['tag']) {
            return false;
        }

        // If A is case-insensitive but B is case-sensitive, B cannot cover A.
        if ($a['modifier'] === 'i' && $b['modifier'] === '') {
            return false;
        }

        // Determine if we should compare values case-insensitively.
        $caseInsensitive = $b['modifier'] === 'i';
        $valA = $caseInsensitive ? strtolower($a['value']) : $a['value'];
        $valB = $caseInsensitive ? strtolower($b['value']) : $b['value'];

        // Exact match
        if ($a['operator'] === $b['operator'] && $valA === $valB) {
            return true;
        }

        // "*="
        if ($b['operator'] === '*=') {
            return str_contains($valA, $valB);
        }

        // "^="
        if ($a['operator'] === '^=' && $b['operator'] === '^=') {
            return str_starts_with($valA, $valB);
        }

        // "$="
        if ($a['operator'] === '$=' && $b['operator'] === '$=') {
            return str_ends_with($valA, $valB);
        }

        // "="
        if ($a['operator'] === '=') {
            if ($b['operator'] === '^=') {
                return str_starts_with($valA, $valB);
            }
            if ($b['operator'] === '$=') {
                return str_ends_with($valA, $valB);
            }
        }

        return false;
    }
}
