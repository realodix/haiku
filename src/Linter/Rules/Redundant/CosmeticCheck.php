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

        $errors = [];
        $rules = [];
        $exactSeen = [];

        // Optimization: Map rules to groups that might interact (cover each other).
        // Standard selectors group by exact selector string.
        // Attribute selectors group by tag and attribute name.
        $interactionMap = [];
        $ghideExceptions = [];

        // Pass 1: Parsing and Collection
        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if (Util::isCommentOrEmpty($line) || str_starts_with($line, '[$')) {
                continue;
            }

            // Extract ghide exceptions into the map; skip to next line if processed
            $ghideDomains = $this->parseGhideDomains($line);
            if ($ghideDomains !== []) {
                foreach ($ghideDomains as $domain) {
                    $ghideExceptions[$domain] = true;
                }

                continue;
            }

            if (!preg_match(Regex::IS_COSMETIC_RULE, $line)) {
                continue;
            }

            // Exact duplicate detection
            if (isset($exactSeen[$line])) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Redundant filter: %s already defined on line %d.',
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
            $domains = $this->parseDomains($domainStr);
            $attrData = $this->parseAttributeSelector($selector);
            $attrKey = $attrData ? $separator.'|'.$attrData['tag'].'|'.$attrData['attr'] : null;

            $ruleIndex = count($rules);
            $rules[] = [
                'line' => $lineNum,
                'content' => $line,
                'domains' => $domains,
                'separator' => $separator,
                'selector' => $selector,
                'attrData' => $attrData,
                'attrKey' => $attrKey,
            ];

            // Use 'A' prefix for Attribute Key and 'S' for Standard Selector Key to avoid collisions
            $interactionKey = $attrKey ? 'A|'.$attrKey : 'S|'.$separator.$selector;
            $interactionMap[$interactionKey][] = $ruleIndex;
        }

        // Pass 2: Redundancy Analysis (Optimized with grouping)
        foreach ($rules as $i => $a) {
            $domains = $a['domains'] ?: ['' => true];
            /** @var array<int, list<string>> */
            $coverageMap = []; // parentLine -> list of covered domains
            /** @var array<int, array<string, mixed>> */
            $parentMap = [];

            $interactionKey = $a['attrKey'] ? 'A|'.$a['attrKey'] : 'S|'.$a['separator'].$a['selector'];

            // Gather candidates: same group (tag+attr) AND global group (attr only)
            $candidates = $interactionMap[$interactionKey] ?? [];
            if ($a['attrData'] && $a['attrData']['tag'] !== '') {
                $globalAttrKey = 'A|'.$a['separator'].'||'.$a['attrData']['attr'];
                if ($globalAttrKey !== $interactionKey) {
                    $candidates = array_merge($candidates, $interactionMap[$globalAttrKey] ?? []);
                }
            }

            foreach ($domains as $domain => $_) {
                /** @var array<string, mixed>|null */
                $bestParent = null;

                foreach ($candidates as $j) {
                    if ($i === $j) {
                        continue;
                    }

                    $b = $rules[$j];

                    if (!$this->isCovered($a, $b, $domain, $ghideExceptions)) {
                        continue;
                    }

                    if ($this->isBetter($b, $a)) {
                        if ($bestParent === null || $this->isBetter($b, $bestParent)) {
                            $bestParent = $b;
                        }
                    }
                }

                if ($bestParent) {
                    $coverageMap[$bestParent['line']][] = $domain;
                    $parentMap[$bestParent['line']] = $bestParent;
                }
            }

            foreach ($coverageMap as $parentLine => $coveredDomains) {
                $parent = $parentMap[$parentLine];
                $allDomainsCovered = count($coveredDomains) === count($domains);

                if ($allDomainsCovered) {
                    $errors[] = $this->buildWholeRuleError($a, $parent);
                } else {
                    foreach ($coveredDomains as $domain) {
                        $errors[] = $this->buildDomainError($a, $parent, $domain);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $parent
     * @return _RuleError
     */
    private function buildWholeRuleError(array $rule, array $parent): array
    {
        $message = '';

        if ($rule['selector'] === $parent['selector']) {
            $message = sprintf(
                'Redundant filter: %s already covered by %s on line %d.',
                $rule['content'],
                $parent['separator'].$parent['selector'],
                $parent['line'],
            );
        } else {
            $message = sprintf(
                'Redundant filter: %s is redundant due to more general selector on line %d.',
                $rule['content'],
                $parent['line'],
            );
        }

        return RuleErrorBuilder::message($message)->line($rule['line'])->build();
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $parent
     * @return _RuleError
     */
    private function buildDomainError(array $rule, array $parent, string $domain): array
    {
        $message = '';

        if ($rule['selector'] === $parent['selector']) {
            $message = sprintf(
                'Redundant filter: domain %s already covered on line %d.',
                $domain, $parent['line'],
            );
        } else {
            $message = sprintf(
                'Redundant filter: domain %s in %s already covered on line %d.',
                $domain,
                $domain.$rule['separator'].$rule['selector'],
                $parent['line'],
            );
        }

        return RuleErrorBuilder::message($message)->line($rule['line'])->build();
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @param array<string, bool> $ghideExceptions
     */
    private function isCovered(array $a, array $b, string $domain, array $ghideExceptions): bool
    {
        if ($a['separator'] !== $b['separator']) {
            return false;
        }

        // B must cover the target domain (either global or specific)
        if ($b['domains'] !== []) {
            if (!isset($b['domains'][$domain])) {
                return false;
            }
        } elseif (isset($ghideExceptions[$domain])) {
            // Global rule B does NOT cover domain if generic hiding is disabled for it.
            return false;
        }

        // Case A: Exact same selector
        if ($a['selector'] === $b['selector']) {
            return true;
        }

        // Case B: Attribute selector dominance
        if ($a['attrData'] && $b['attrData']) {
            return $this->isAttrCoveredBy($a['attrData'], $b['attrData']);
        }

        return false;
    }

    /**
     * Determine if parent B is "better" (more general or earlier) than parent C.
     *
     * @param array<string, mixed> $b
     * @param array<string, mixed> $c
     */
    private function isBetter(array $b, array $c): bool
    {
        // 1. Semantic generality (for attribute selectors)
        if ($b['attrData'] && $c['attrData']) {
            $bCoversC = $this->isAttrCoveredBy($c['attrData'], $b['attrData']);
            $cCoversB = $this->isAttrCoveredBy($b['attrData'], $c['attrData']);

            if ($bCoversC && !$cCoversB) {
                return true; // B is strictly more general
            }
            if (!$bCoversC && $cCoversB) {
                return false; // C is strictly more general
            }
        }

        // 2. Globalness (Global rules are better references than domain-specific ones)
        if ($b['domains'] === [] && $c['domains'] !== []) {
            return true;
        }
        if ($b['domains'] !== [] && $c['domains'] === []) {
            return false;
        }

        // 3. Line number (Earlier rules are preferred as reference points)
        return $b['line'] < $c['line'];
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
     * Parse a line for ghide exceptions.
     *
     * @param string $line The line to parse.
     * @return list<string> Returns a list of domains, or an empty list if not a ghide rule.
     */
    private function parseGhideDomains(string $line): array
    {
        if (!str_starts_with($line, '@@')) {
            return [];
        }

        // Form 1: @@||example.com^$ghide
        if (preg_match('/^@@\|\|([a-z0-9.-]+)\^?\$g(?:eneric)?hide(?:,|$)/i', $line, $m)) {
            return [strtolower($m[1])];
        }

        // Form 2: @@*$ghide,domain=example.com
        if (preg_match(Regex::NET_OPTION, $line, $m)) {
            $options = Util::splitOptions($m[2]);
            $isGhide = false;
            $domains = [];

            foreach ($options as $opt) {
                $opt = trim($opt);
                $opt = strtolower($opt);

                if (in_array($opt, ['ghide', 'generichide'], true)) {
                    $isGhide = true;

                    continue;
                }

                if (preg_match('/^(domain|from|to)=(.+)$/i', $opt, $dm)) {
                    $domains = explode('|', $dm[2]);
                }
            }

            if ($isGhide && $domains !== []) {
                $validDomains = [];
                foreach ($domains as $d) {
                    $d = trim($d);
                    if ($d !== '' && !str_starts_with($d, '~')) {
                        $validDomains[] = strtolower($d);
                    }
                }

                return $validDomains;
            }
        }

        return [];
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
        // Explicit attribute selector: tag[attr op "value" mod]
        if (preg_match(
            '/^(?:(?<tag>[a-z0-9_-]+))?\[(?<attr>[a-z0-9_-]+)\s*(?<op>\^=|\$=|\*=|=|~=)\s*"(?<val>[^"]+)"\s*(?<mod>i)?\]$/i',
            $selector,
            $m,
        )) {
            return [
                'tag' => strtolower($m['tag']),
                'attr' => strtolower($m['attr']),
                'operator' => $m['op'],
                'value' => $m['val'],
                'modifier' => strtolower($m['mod'] ?? ''),
            ];
        }

        // Class selector: [tag]?.className
        if (preg_match('/^(?:(?<tag>[a-z0-9_-]+))?\.(?<val>[a-z0-9_-]+)$/i', $selector, $m)) {
            return [
                'tag' => strtolower($m['tag']),
                'attr' => 'class',
                'operator' => '~=',
                'value' => $m['val'],
                'modifier' => '',
            ];
        }

        // ID selector: [tag]?#idName
        if (preg_match('/^(?:(?<tag>[a-z0-9_-]+))?#(?<val>[a-z0-9_-]+)$/i', $selector, $m)) {
            return [
                'tag' => strtolower($m['tag']),
                'attr' => 'id',
                'operator' => '=',
                'value' => $m['val'],
                'modifier' => '',
            ];
        }

        return null;
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
        if ($a['attr'] !== $b['attr']) {
            return false;
        }

        // B covers A if B has no tag (global) or same tag as A.
        if ($b['tag'] !== '' && $a['tag'] !== $b['tag']) {
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

        // Exact match of operator and value
        if ($a['operator'] === $b['operator'] && $valA === $valB) {
            return true;
        }

        // B: "*=" (substring)
        // Covers any A where the matched value A contains substring B.
        if ($b['operator'] === '*=') {
            return str_contains($valA, $valB);
        }

        // B: "^=" (starts with)
        // Covers A if A is "=" or "^=" and valA starts with valB.
        // Note: For 'class' attributes, A=.cls translates to A[class~="cls"],
        // which matches ANY word in the class list. Since [class^="val"] only
        // matches if 'val' is at the very beginning of the string, it does
        // NOT cover [class~="cls"] unless the class is guaranteed to be first.
        // Conversely, for 'id' attributes (which are single-valued),
        // #id translates to [id="id"], which IS covered by [id^="val"].
        if ($b['operator'] === '^=') {
            return ($a['operator'] === '=' || $a['operator'] === '^=')
                && str_starts_with($valA, $valB);
        }

        // B: "$=" (ends with)
        // Covers A if A is "=" or "$=" and valA ends with valB.
        // Same logic as ^=: Covers ID selectors but not Class selectors.
        if ($b['operator'] === '$=') {
            return ($a['operator'] === '=' || $a['operator'] === '$=')
                && str_ends_with($valA, $valB);
        }

        // B: "~=" (whitespace-separated item)
        // Covers A if values match exactly (A is "=" or "~=")
        // OR covers A if A is "=" and B's value is a word in A's value.
        if ($b['operator'] === '~=') {
            if ($a['operator'] === '~=') {
                return $valA === $valB;
            }
            if ($a['operator'] === '=') {
                $words = preg_split('/\s+/', $valA);

                return in_array($valB, $words, true);
            }
        }

        return false;
    }
}
