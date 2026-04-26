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
 *  tag: string,
 *  attr: string,
 *  operator: string,
 *  value: string,
 *  modifier: string
 * }
 * @phpstan-type _SeenAttrSelector array{
 *  data: _ParsedAttrSelector,
 *  line: int
 * }
 * @phpstan-type _CosmeticRuleData array{
 *  lineNum: int,
 *  line: string,
 *  domains: array<string, bool>,
 *  separator: string,
 *  selector: string,
 *  attrData: _ParsedAttrSelector|null
 * }
 */
final class CosmeticCheck implements Rule
{
    /** @var array<string, int> */
    private array $exactSeen = [];

    /** @var list<_CosmeticRuleData> */
    private array $rulesData = [];

    /** @var array<string, list<int>> */
    private array $interactionMap = [];

    /** @var array<string, bool> */
    private array $ghideExceptions = [];

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

        $this->reset();
        $errors = [];

        // Pass 1: Parsing and Collection
        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if (Util::isCommentOrEmpty($line) || str_starts_with($line, '[$')) {
                continue;
            }

            // Extract ghide exceptions into the map
            $ghideDomains = $this->parseGhideDomains($line);
            if ($ghideDomains !== []) {
                foreach ($ghideDomains as $domain) {
                    $this->ghideExceptions[$domain] = true;
                }

                continue;
            }

            if (!preg_match(Regex::COSMETIC_RULE, $line, $m)) {
                continue;
            }

            $domainStr = trim($m[3]);
            $separator = $m[4];
            $selector = $m[5];
            $domains = $this->parseDomains($domainStr);
            $attrData = $this->parseAttributeSelector($selector);

            $ruleIndex = count($this->rulesData);
            $this->rulesData[$ruleIndex] = [
                'lineNum' => $lineNum,
                'line' => $line,
                'domains' => $domains,
                'separator' => $separator,
                'selector' => $selector,
                'attrData' => $attrData,
            ];

            // Group rules into buckets to avoid O(N^2) redundancy checks.
            // Prefixes used to avoid key collisions:
            // 'A' (Attribute Selector), 'S' (Standard Selector)
            // 'E' (Exact Match), 'P' (Partial Match)
            if ($attrData) {
                $val = strtolower($attrData['value']);
                $op = $attrData['operator'];
                $tag = $attrData['tag'];
                $attr = $attrData['attr'];

                if (in_array($op, ['^=', '$=', '*='], true)) {
                    // Partial bucket (A|P): Groups rules with wildcard operators by their tag and attribute name.
                    // Example: [class*="ad"] goes here.
                    $partialKey = 'A|P|'.$separator.'|'.$tag.'|'.$attr;
                    $this->interactionMap[$partialKey][] = $ruleIndex;
                } else {
                    // Exact bucket (A|E): Groups rules with exact operators (=, ~=) by their specific value.
                    // Example: .ads and [class="ads"] go here, drastically reducing candidate pool.
                    $exactKey = 'A|E|'.$separator.'|'.$tag.'|'.$attr.'|'.$val;
                    $this->interactionMap[$exactKey][] = $ruleIndex;
                }
            } else {
                // Standard bucket (S): Groups unparsed selectors by their exact string.
                $this->interactionMap['S|'.$separator.$selector][] = $ruleIndex;
            }
        }

        // Pass 2: Redundancy Analysis (Optimized with grouping)
        foreach ($this->rulesData as $currentIndex => $currentRule) {
            // 1. Exact duplicate check
            if ($err = $this->checkExactDuplicate($currentRule)) {
                $errors[] = $err;

                continue;
            }

            // 2. Global redundancy check (checks if the entire rule is covered by another)
            if ($err = $this->checkGlobalRedundancy($currentIndex, $currentRule)) {
                $errors[] = $err;

                continue;
            }

            // 3. Domain level redundancy (only for rules that specify domains)
            if ($currentRule['domains'] !== []) {
                $errors = [...$errors, ...$this->checkDomainRedundancy($currentIndex, $currentRule)];
            }
        }

        return $errors;
    }

    private function reset(): void
    {
        $this->exactSeen = [];
        $this->rulesData = [];
        $this->interactionMap = [];
        $this->ghideExceptions = [];
    }

    /**
     * @param _CosmeticRuleData $currentRule
     * @return _RuleError|null
     */
    private function checkExactDuplicate(array $currentRule): ?array
    {
        $line = $currentRule['line'];
        if (isset($this->exactSeen[$line])) {
            return RuleErrorBuilder::message(sprintf(
                'Redundant filter: %s already defined on line %d.',
                $line, $this->exactSeen[$line],
            ))->line($currentRule['lineNum'])->build();
        }

        $this->exactSeen[$line] = $currentRule['lineNum'];

        return null;
    }

    /**
     * @param _CosmeticRuleData $currentRule
     * @return _RuleError|null
     */
    private function checkGlobalRedundancy(int $currentIndex, array $currentRule): ?array
    {
        $domains = $currentRule['domains'] ?: ['' => true];
        $candidates = $this->findCandidates($currentRule, $this->interactionMap);

        /** @var array<string, mixed>|null $bestParent */
        $bestParent = null;

        foreach ($candidates as $candidateIndex) {
            if ($currentIndex === $candidateIndex) {
                continue;
            }

            $candidate = $this->rulesData[$candidateIndex];

            // A candidate can only cover the entire rule if it covers every domain.
            $coversAll = true;
            foreach ($domains as $domain => $_) {
                if (!$this->isCovered($currentRule, $candidate, $domain, $this->ghideExceptions)) {
                    $coversAll = false;

                    break;
                }
            }

            if ($coversAll && $this->isBetter($candidate, $currentRule)) {
                if ($bestParent === null || $this->isBetter($candidate, $bestParent)) {
                    $bestParent = $candidate;
                }
            }
        }

        if ($bestParent) {
            return $this->buildWholeRuleError($currentRule, $bestParent);
        }

        return null;
    }

    /**
     * @param _CosmeticRuleData $currentRule
     * @return list<_RuleError>
     */
    private function checkDomainRedundancy(int $currentIndex, array $currentRule): array
    {
        $errors = [];
        $candidates = $this->findCandidates($currentRule, $this->interactionMap);
        $coverageMap = [];
        $parentMap = [];

        foreach ($currentRule['domains'] as $domain => $_) {
            $bestParent = null;

            foreach ($candidates as $candidateIndex) {
                if ($currentIndex === $candidateIndex) {
                    continue;
                }

                $candidate = $this->rulesData[$candidateIndex];

                if ($this->isCovered($currentRule, $candidate, $domain, $this->ghideExceptions)) {
                    if ($this->isBetter($candidate, $currentRule)) {
                        if ($bestParent === null || $this->isBetter($candidate, $bestParent)) {
                            $bestParent = $candidate;
                        }
                    }
                }
            }

            if ($bestParent) {
                $coverageMap[$bestParent['lineNum']][] = $domain;
                $parentMap[$bestParent['lineNum']] = $bestParent;
            }
        }

        foreach ($coverageMap as $parentLine => $coveredDomains) {
            $parent = $parentMap[$parentLine];
            foreach ($coveredDomains as $domain) {
                $errors[] = $this->buildDomainError($currentRule, $parent, $domain);
            }
        }

        return $errors;
    }

    /**
     * @param _CosmeticRuleData $rule
     * @param _CosmeticRuleData $parent
     * @return _RuleError
     */
    private function buildWholeRuleError(array $rule, array $parent): array
    {
        $message = '';

        if ($rule['selector'] === $parent['selector']) {
            $content = $rule['line'];
            if (count($rule['domains']) > 2) {
                $content = '...,'.array_key_last($rule['domains'])
                    .$rule['separator'].$rule['selector'];
            }

            $message = sprintf(
                'Redundant filter: %s already covered by %s on line %d.',
                $content,
                $parent['separator'].$parent['selector'],
                $parent['lineNum'],
            );
        } else {
            $message = sprintf(
                'Redundant filter: %s is redundant due to more general selector on line %d.',
                $rule['line'], $parent['lineNum'],
            );
        }

        return RuleErrorBuilder::message($message)->line($rule['lineNum'])->build();
    }

    /**
     * @param _CosmeticRuleData $rule
     * @param _CosmeticRuleData $parent
     * @return _RuleError
     */
    private function buildDomainError(array $rule, array $parent, string $domain): array
    {
        $message = '';

        if ($rule['selector'] === $parent['selector']) {
            $message = sprintf(
                'Redundant filter: domain %s already covered on line %d.',
                $domain, $parent['lineNum'],
            );
        } else {
            $message = sprintf(
                'Redundant filter: domain %s in %s already covered on line %d.',
                $domain,
                $domain.$rule['separator'].$rule['selector'],
                $parent['lineNum'],
            );
        }

        return RuleErrorBuilder::message($message)->line($rule['lineNum'])->build();
    }

    /**
     * Identify potential candidate rules that could cover the current rule.
     *
     * This uses the interaction map to narrow down the pool of candidates from
     * O(N) to a much smaller set of rules that share relevant characteristics
     * (e.g., same tag, attribute, or selector).
     *
     * @param _CosmeticRuleData $currentRule The rule being checked.
     * @param array<string, list<int>> $interactionMap Map of grouped rule indices.
     * @return list<int> List of candidate rule indices.
     */
    private function findCandidates(array $currentRule, array $interactionMap): array
    {
        $candidates = [];
        $separator = $currentRule['separator'];

        if ($currentRule['attrData']) {
            $val = strtolower($currentRule['attrData']['value']);
            $op = $currentRule['attrData']['operator'];
            $tag = $currentRule['attrData']['tag'];
            $attr = $currentRule['attrData']['attr'];

            // 1. Exact Candidates
            $exactKey = 'A|E|'.$separator.'|'.$tag.'|'.$attr.'|'.$val;
            if (isset($interactionMap[$exactKey])) {
                $candidates = array_merge($candidates, $interactionMap[$exactKey]);
            }

            // 1b. Word Candidates (if A is '=')
            $words = $op === '=' ? (preg_split('/\s+/', $val) ?: []) : [];
            if ($op === '=') {
                foreach ($words as $word) {
                    if ($word === '' || $word === $val) {
                        continue;
                    }
                    $wordKey = 'A|E|'.$separator.'|'.$tag.'|'.$attr.'|'.$word;
                    if (isset($interactionMap[$wordKey])) {
                        $candidates = array_merge($candidates, $interactionMap[$wordKey]);
                    }
                }
            }

            // 2. Partial Candidates
            $partialKey = 'A|P|'.$separator.'|'.$tag.'|'.$attr;
            if (isset($interactionMap[$partialKey])) {
                $candidates = array_merge($candidates, $interactionMap[$partialKey]);
            }

            // 3. Global Candidates (if A has a tag)
            if ($tag !== '') {
                $globalExactKey = 'A|E|'.$separator.'||'.$attr.'|'.$val;
                if (isset($interactionMap[$globalExactKey])) {
                    $candidates = array_merge($candidates, $interactionMap[$globalExactKey]);
                }

                if ($op === '=') {
                    foreach ($words as $word) {
                        if ($word === '' || $word === $val) {
                            continue;
                        }
                        $globalWordKey = 'A|E|'.$separator.'||'.$attr.'|'.$word;
                        if (isset($interactionMap[$globalWordKey])) {
                            $candidates = array_merge($candidates, $interactionMap[$globalWordKey]);
                        }
                    }
                }

                $globalPartialKey = 'A|P|'.$separator.'||'.$attr;
                if (isset($interactionMap[$globalPartialKey])) {
                    $candidates = array_merge($candidates, $interactionMap[$globalPartialKey]);
                }
            }

            return array_unique($candidates);
        }

        return $interactionMap['S|'.$separator.$currentRule['selector']] ?? [];
    }

    /**
     * Determine if the rule is covered by the candidate rule for a specific domain.
     *
     * @param _CosmeticRuleData $rule The rule being checked.
     * @param _CosmeticRuleData $candidate The candidate rule that might cover it.
     * @param array<string, bool> $ghideExceptions
     */
    private function isCovered(array $rule, array $candidate, string $domain, array $ghideExceptions): bool
    {
        if ($rule['separator'] !== $candidate['separator']) {
            return false;
        }

        // $candidate must cover the target domain (either global or specific)
        if ($candidate['domains'] !== []) {
            if (!isset($candidate['domains'][$domain])) {
                return false;
            }
        } elseif (isset($ghideExceptions[$domain])) {
            // Global rule $candidate does NOT cover domain if generic hiding is disabled for it.
            return false;
        }

        // Case A: Exact same selector
        if ($rule['selector'] === $candidate['selector']) {
            return true;
        }

        // Case B: Attribute selector dominance
        if ($rule['attrData'] && $candidate['attrData']) {
            return $this->isAttrCoveredBy($rule['attrData'], $candidate['attrData']);
        }

        return false;
    }

    /**
     * Determine if the candidate rule is "better" (more general or earlier) than the current best.
     *
     * @param _CosmeticRuleData $candidate The rule to evaluate.
     * @param _CosmeticRuleData $best The current best rule to compare against.
     */
    private function isBetter(array $candidate, array $best): bool
    {
        // 1. Semantic generality (for attribute selectors)
        if ($candidate['attrData'] && $best['attrData']) {
            $bCoversC = $this->isAttrCoveredBy($best['attrData'], $candidate['attrData']);
            $cCoversB = $this->isAttrCoveredBy($candidate['attrData'], $best['attrData']);

            if ($bCoversC && !$cCoversB) {
                return true; // candidate is strictly more general
            }
            if (!$bCoversC && $cCoversB) {
                return false; // best is strictly more general
            }
        }

        // 2. Globalness (Global rules are better references than domain-specific ones)
        if ($candidate['domains'] === [] && $best['domains'] !== []) {
            return true;
        }
        if ($candidate['domains'] !== [] && $best['domains'] === []) {
            return false;
        }

        // 3. Line number (Earlier rules are preferred as reference points)
        return $candidate['lineNum'] < $best['lineNum'];
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
     * Determine whether attribute selector "rule" is semantically covered by selector "candidate".
     *
     * The rule is considered covered by the candidate if every element matched by the rule would
     * also be matched by the candidate.
     *
     * This only applies to simple attribute selectors with the same tag and
     * attribute name.
     *
     * Examples:
     *   [href="abc"]    is covered by [href*="a"]
     *   [href^="https"] is covered by [href*="http"]
     *
     * @param _ParsedAttrSelector $rule The rule being checked.
     * @param _ParsedAttrSelector $candidate The candidate rule that might cover it.
     */
    private function isAttrCoveredBy(array $rule, array $candidate): bool
    {
        if ($rule['attr'] !== $candidate['attr']) {
            return false;
        }

        // $candidate covers $rule if $candidate has no tag (global) or same tag as $rule.
        if ($candidate['tag'] !== '' && $rule['tag'] !== $candidate['tag']) {
            return false;
        }

        // If $rule is case-insensitive but $candidate is case-sensitive, $candidate cannot cover $rule.
        if ($rule['modifier'] === 'i' && $candidate['modifier'] === '') {
            return false;
        }

        // Determine if we should compare values case-insensitively.
        $caseInsensitive = $candidate['modifier'] === 'i';
        $valR = $caseInsensitive ? strtolower($rule['value']) : $rule['value'];
        $valC = $caseInsensitive ? strtolower($candidate['value']) : $candidate['value'];

        // Exact match of operator and value
        if ($rule['operator'] === $candidate['operator'] && $valR === $valC) {
            return true;
        }

        // $candidate: "*=" (substring)
        // Covers any $rule where the matched value $rule contains substring $candidate.
        if ($candidate['operator'] === '*=') {
            return str_contains($valR, $valC);
        }

        // $candidate operator "^=" (starts with)
        // Covers $rule if:
        // - $rule uses "=" or "^="
        // - AND $rule's value starts with $candidate's value
        //
        // Note:
        // - For 'class' attributes, ".cls" translates to $rule[class~="cls"],
        //   which matches ANY word in the class list. Since [class^="val"] only
        //   matches if 'val' is at the very beginning of the string, it does
        //   NOT cover [class~="cls"] unless the class is guaranteed to be first.
        // - For id attributes (single value), "#id" behaves like [id="id"],
        //   which CAN be covered.
        if ($candidate['operator'] === '^=') {
            return ($rule['operator'] === '=' || $rule['operator'] === '^=')
                && str_starts_with($valR, $valC);
        }

        // $candidate: "$=" (ends with)
        // Covers $rule if $rule is "=" or "$=" and $rule's value ends with $candidate's value.
        // Same logic as ^=: Covers ID selectors but not Class selectors.
        if ($candidate['operator'] === '$=') {
            return ($rule['operator'] === '=' || $rule['operator'] === '$=')
                && str_ends_with($valR, $valC);
        }

        // $candidate: "~=" (whitespace-separated item)
        // Covers $rule if values match exactly ($rule is "=" or "~=")
        // OR covers $rule if $rule is "=" and $candidate's value is a word in $rule's value.
        if ($candidate['operator'] === '~=') {
            if ($rule['operator'] === '~=') {
                return $valR === $valC;
            }
            if ($rule['operator'] === '=') {
                $words = preg_split('/\s+/', $valR);

                return in_array($valC, $words, true);
            }
        }

        return false;
    }
}
