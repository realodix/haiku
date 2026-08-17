<?php

namespace Realodix\Haiku\Linter\Rules\Redundant;

use Illuminate\Support\Str;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Support\Util;

/**
 * @phpstan-type _ParsedAttrSelector array{
 *  tag: string,
 *  attr: string,
 *  operator: string,
 *  value: string,
 *  modifier: string
 * }
 * @phpstan-type _ParsedSimpleSelector array{
 *  tag: string,
 *  id: string,
 *  classes: list<string>
 * }
 * @phpstan-type _CosmeticRule array{
 *  lineNum: int,
 *  line: string,
 *  domains: array<string, bool>,
 *  separator: string,
 *  selector: string,
 *  attrData: _ParsedAttrSelector|null,
 *  parsedSimple: _ParsedSimpleSelector|null,
 *  canonicalSelector: string,
 *  hasMixedDomains: bool,
 *  isAlmostGlobal: bool,
 *  conditionKey: string,
 * }
 */
final class CosmeticCheck implements Rule
{
    private const ATTR_PARTIAL_KEY_LEN = 2;

    private const MAX_SELECTOR_COMPONENT = 12;

    /** @var array<string, int> */
    private array $exactSeen = [];

    /** @var list<_CosmeticRule> */
    private array $collection = [];

    /** @var array<string, list<int>> */
    private array $interactionMap = [];

    /** @var array<string, bool> */
    private array $ghideExceptions = [];

    public function __construct(
        private LinterConfig $config,
        private ConditionalScope $scope,
    ) {}

    public function check(array $content, $err): array
    {
        if (!$this->config->rules['no_dupe_rules']) {
            return [];
        }

        $conditionKeys = $this->scope->process($content);

        // =====================================================================
        // Pass 1: Parsing and Collection
        // =====================================================================
        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            $conditionKey = $conditionKeys[$index];
            if ($conditionKey === null) {
                continue;
            }

            if (Util::isCommentOrEmpty($line) || str_starts_with($line, '[$')) {
                continue;
            }

            // Collect generic-hide exception domains so that global rules are
            // not considered as covering those domains in later checks.
            $ghideDomains = $this->parseDomainExceptRuleOpt($line);
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
            $parsedSimple = $this->parseSimpleSelector($selector);

            // Pre-calculate domain status to optimize the isCovered() hot path.
            $isMixed = $this->isMixedDomains($domains);
            $isAlmostGlobal = false;
            if (!$isMixed && $domains !== []) {
                // An "almost global" rule contains only exclusions (negated domains,
                // prefixed with ~).
                $firstDomain = (string) array_key_first($domains);
                $isAlmostGlobal = $firstDomain !== '' && $firstDomain[0] === '~';
            }

            $entry = [
                'lineNum' => $lineNum,
                'line' => $line,
                'domains' => $domains,
                'separator' => $separator,
                'selector' => $selector,
                'attrData' => $attrData,
                'parsedSimple' => $parsedSimple,
                'canonicalSelector' => $this->getCanonicalSelector($selector, $parsedSimple),
                'hasMixedDomains' => $isMixed,
                'isAlmostGlobal' => $isAlmostGlobal,
                'conditionKey' => $conditionKey,
            ];
            $this->collection[$lineNum] = $entry;

            // Group rules into interaction buckets:
            // 'A' (Attribute Selector), 'S' (Standard Selector)
            // 'E' (Exact Match), 'P' (Partial Match)
            if ($attrData) {
                $val = strtolower($attrData['value']);
                $op = $attrData['operator'];
                $tag = $attrData['tag'];
                $attr = $attrData['attr'];

                if (in_array($op, ['^=', '$=', '*='], true)) {
                    // Partial bucket (P)
                    // Example: div[class*="ad"]
                    $partialKey = $this->buildAttrKey('P', $separator, $tag, $attr, $op, $val);
                    $this->interactionMap[$partialKey][] = $lineNum;
                } else {
                    // Exact bucket (E): groups exact-operator rules (=, ~=).
                    // Example: .ads and [class="ads"]
                    $exactKey = $this->buildAttrKey('E', $separator, $tag, $attr, $op, $val);
                    $this->interactionMap[$exactKey][] = $lineNum;
                }
            }

            // Standard bucket (S): Groups rules with standard selectors by their canonical form.
            if ($parsedSimple !== null) {
                $this->interactionMap['S|'.$separator.$entry['canonicalSelector']][] = $lineNum;
            }
        }

        // =====================================================================
        // Pass 2: Redundancy Analysis
        // =====================================================================
        foreach ($this->collection as $entry) {
            if ($this->checkExactDuplicate($err, $entry)) {
                continue;
            }

            if ($this->checkGlobalRedundancy($err, $entry)) {
                continue;
            }

            if ($entry['domains'] !== []) {
                $this->checkDomainRedundancy($err, $entry);
            }
        }

        $this->reset();

        return $err->toArray();
    }

    /**
     * Resets all internal state so the checker can be reused across files.
     */
    private function reset(): void
    {
        $this->collection = [];
        $this->exactSeen = [];
        $this->interactionMap = [];
        $this->ghideExceptions = [];
    }

    /**
     * Checks whether the given rule is an exact duplicate of a previously seen rule.
     *
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param _CosmeticRule $entry
     */
    private function checkExactDuplicate($err, array $entry): bool
    {
        $line = $entry['line'];
        $domainStr = implode(',', array_keys($entry['domains']));
        $key = $domainStr.'|'.$entry['separator'].'|'.$entry['canonicalSelector'].'|'.$entry['conditionKey'];

        if (isset($this->exactSeen[$key])) {
            $err->message(sprintf(
                'Redundant filter: %s already defined on line %d',
                $line, $this->exactSeen[$key],
            ))->line($entry['lineNum'])->build();

            return true;
        }

        $this->exactSeen[$key] = $entry['lineNum'];

        return false;
    }

    /**
     * Checks whether the entire rule is made redundant by a global rule.
     *
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param _CosmeticRule $entry
     */
    private function checkGlobalRedundancy($err, array $entry): bool
    {
        $domains = $entry['domains'] ?: ['' => true];
        $candidates = $this->findCandidates($entry, $this->interactionMap);

        /** @var _CosmeticRule|null */
        $bestParent = null;

        foreach ($candidates as $candidateIndex) {
            if ($candidateIndex === $entry['lineNum']) {
                continue;
            }

            $candidate = $this->collection[$candidateIndex];

            // The candidate must cover every domain of the current rule.
            $coversAllDomains = true;
            foreach ($domains as $domain => $_) {
                if (!$this->isCovered($entry, $candidate, $domain, $this->ghideExceptions)) {
                    $coversAllDomains = false;
                    break;
                }
            }

            if (!$coversAllDomains || !$this->isBetter($candidate, $entry)) {
                continue;
            }

            // Track the "best" (most general) parent for a clearer message.
            if ($bestParent === null || $this->isBetter($candidate, $bestParent)) {
                $bestParent = $candidate;
            }
        }

        if ($bestParent) {
            $message = '';
            if ($entry['selector'] === $bestParent['selector']) {
                $content = $entry['line'];
                if (count($entry['domains']) > 2) {
                    $content = '...,'.array_key_last($entry['domains'])
                        .$entry['separator'].$entry['selector'];
                }

                $message = sprintf(
                    'Redundant filter: %s already covered by %s on line %d',
                    $content,
                    $bestParent['separator'].$bestParent['selector'],
                    $bestParent['lineNum'],
                );
            } else {
                $message = sprintf(
                    'Redundant filter: %s is redundant due to more general selector on line %d',
                    Str::limit($entry['line'], 50), $bestParent['lineNum'],
                );
            }

            $err->message($message)->line($entry['lineNum'])->build();

            return true;
        }

        return false;
    }

    /**
     * Checks for domain-level redundancy.
     *
     * Example: `example.com,example.org##.ads` and `example.com##.ads`
     *
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param _CosmeticRule $entry
     */
    private function checkDomainRedundancy($err, array $entry): void
    {
        $domains = array_keys($entry['domains']);

        // Phase 1: Internal coverage (single line)
        $internallyCoveredDomains = [];
        foreach (DomainCoverage::findCovered($domains) as $domain => $coveringDomain) {
            $internallyCoveredDomains[] = $domain;
            $coveringDomain .= !str_contains($coveringDomain, '.') ? ' TLD' : '';

            $err->message(sprintf('Redundant domain: %s is covered by %s', $domain, $coveringDomain))
                ->line($entry['lineNum'])
                ->build();
        }

        // Phase 2: External coverage (multi lines)
        $candidates = $this->findCandidates($entry, $this->interactionMap);
        $coverageMap = [];
        $parentMap = [];

        foreach ($entry['domains'] as $domain => $_) {
            // Skip domains already flagged by internal coverage
            if (in_array($domain, $internallyCoveredDomains, true)) {
                continue;
            }

            $bestParent = null;

            foreach ($candidates as $candidateIndex) {
                if ($entry['lineNum'] === $candidateIndex) {
                    continue;
                }

                $candidate = $this->collection[$candidateIndex];

                if ($this->isCovered($entry, $candidate, $domain, $this->ghideExceptions)) {
                    if ($this->isBetter($candidate, $entry)) {
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
                $message = '';
                if ($entry['selector'] === $parent['selector']) {
                    $message = sprintf(
                        'Redundant filter: domain %s already covered on line %d',
                        $domain, $parent['lineNum'],
                    );
                } else {
                    $message = sprintf(
                        'Redundant filter: domain %s in %s already covered on line %d',
                        $domain,
                        $domain.$entry['separator'].$entry['selector'],
                        $parent['lineNum'],
                    );
                }

                $err->message($message)->line($entry['lineNum'])->build();
            }
        }
    }

    /**
     * Identifies potential candidate rules that could cover the current rule.
     *
     * Uses the interaction map to narrow down the candidate pool from O(N) to
     * a much smaller set of rules that share relevant characteristics (e.g.,
     * same tag, attribute, or canonical selector).
     *
     * @param _CosmeticRule $entry The rule being checked.
     * @param array<string, list<int>> $interactionMap Map of grouped rule indices.
     * @return list<int> List of candidate rule indices (line numbers).
     */
    private function findCandidates(array $entry, array $interactionMap): array
    {
        $candidates = [];
        $separator = $entry['separator'];

        // -----------------------------------------------------------------
        // Attribute selector candidates
        // -----------------------------------------------------------------
        if ($entry['attrData']) {
            $val = strtolower($entry['attrData']['value']);
            $op = $entry['attrData']['operator'];
            $tag = $entry['attrData']['tag'];
            $attr = $entry['attrData']['attr'];

            // 1. Exact candidates
            $exactKey = $this->buildAttrKey('E', $separator, $tag, $attr, val: $val);
            if (isset($interactionMap[$exactKey])) {
                array_push($candidates, ...$interactionMap[$exactKey]);
            }

            // 1b. Word candidates: when the operator is '='.
            $words = $op === '=' ? (preg_split('/\s+/', $val) ?: []) : [];
            foreach ($words as $word) {
                if ($word === '' || $word === $val) {
                    continue;
                }

                $wordKey = $this->buildAttrKey('E', $separator, $tag, $attr, val: $word);
                if (isset($interactionMap[$wordKey])) {
                    array_push($candidates, ...$interactionMap[$wordKey]);
                }
            }

            // 2. Partial Candidates
            $targetOps = match ($op) {
                '='  => ['*=', '^=', '$='],
                '~=' => ['*='],
                '^=' => ['*=', '^='],
                '$=' => ['*=', '$='],
                '*=' => ['*='],
                default => [],
            };

            foreach ($targetOps as $tOp) {
                $pKey = $this->buildAttrKey('P', $separator, $tag, $attr, $tOp, $val);
                if (isset($interactionMap[$pKey])) {
                    array_push($candidates, ...$interactionMap[$pKey]);
                }
            }

            // 3. Global candidates: rules without a tag qualifier that could
            // cover tag-specific rules.
            if ($tag !== '') {
                // Global Exact
                $geKey = $this->buildAttrKey('G|E', $separator, $tag, $attr, val: $val);
                if (isset($interactionMap[$geKey])) {
                    array_push($candidates, ...$interactionMap[$geKey]);
                }

                // Global Word
                foreach ($words as $word) {
                    if ($word === '' || $word === $val) {
                        continue;
                    }

                    $globalWordKey = $this->buildAttrKey('G|E', $separator, $tag, $attr, val: $word);
                    if (isset($interactionMap[$globalWordKey])) {
                        array_push($candidates, ...$interactionMap[$globalWordKey]);
                    }
                }

                // Global Partial
                foreach ($targetOps as $tOp) {
                    $gpKey = $this->buildAttrKey('G|P', $separator, $tag, $attr, $tOp, $val);
                    if (isset($interactionMap[$gpKey])) {
                        array_push($candidates, ...$interactionMap[$gpKey]);
                    }
                }
            }

            $candidates = array_unique($candidates);
            $candidates = array_values(array_filter($candidates, function ($idx) use ($entry) {
                return $this->collection[$idx]['conditionKey'] === $entry['conditionKey'];
            }));

            return $candidates;
        }

        // -----------------------------------------------------------------
        // Standard (non-attribute) selector candidates
        // -----------------------------------------------------------------

        // Look up the canonical bucket. Canonicalization normalizes class
        // order so that .a.b and .b.a resolve to the same bucket.
        $parsed = $entry['parsedSimple'];
        $key = 'S|'.$separator.$entry['canonicalSelector'];
        if (isset($interactionMap[$key])) {
            $candidates = array_merge($candidates, $interactionMap[$key]);
        }

        // Subset scan: find more general simple selectors whose class list is
        // a proper subset of the current rule's classes.
        // Example: given the rule `.ad.banner.text`, a candidate `.ad` or
        // `.ad.banner` is more general and therefore covers it.
        if ($parsed !== null) {
            $components = [];
            if ($parsed['tag'] !== '') {
                $components[] = $parsed['tag'];
            }

            if ($parsed['id'] !== '') {
                $components[] = '#'.$parsed['id'];
            }

            foreach ($parsed['classes'] as $cls) {
                $components[] = '.'.$cls;
            }

            $componentCount = count($components);
            if ($componentCount <= self::MAX_SELECTOR_COMPONENT) {
                // Process ALL subsets (without size restrictions)
                for ($mask = 0; $mask < (1 << $componentCount); $mask++) {
                    if ($mask === (1 << $componentCount) - 1) {
                        continue; // skip full set
                    }

                    $subset = [];
                    for ($i = 0; $i < $componentCount; $i++) {
                        if ($mask & (1 << $i)) {
                            $subset[] = $components[$i];
                        }
                    }

                    $subCanonical = implode('', $subset);
                    $subKey = 'S|'.$separator.$subCanonical;
                    if (isset($interactionMap[$subKey])) {
                        foreach ($interactionMap[$subKey] as $idx) {
                            if ($idx === $entry['lineNum']) {
                                continue;
                            }
                            $cand = $this->collection[$idx];
                            if ($cand['parsedSimple'] !== null) {
                                $candidates[] = $idx;
                            }
                        }
                    }
                }
            }
        }

        $candidates = array_unique($candidates);
        $candidates = array_values(array_filter($candidates, function ($idx) use ($entry) {
            return $this->collection[$idx]['conditionKey'] === $entry['conditionKey'];
        }));

        return $candidates;
    }

    /**
     * Determines whether a cosmetic rule is covered by a candidate rule for a
     * specific domain.
     *
     * A rule is covered if:
     * 1. They share the same separator (e.g. ##, #@#).
     * 2. Rules with mixed domains (~ and +) only cover rules with the exact same
     *    domain set.
     * 3. The candidate's domain list encompasses the target domain.
     * 4. The candidate's selector is identical to or strictly more general
     *    than the target rule's selector.
     *
     * @param _CosmeticRule $rule The rule being checked for redundancy.
     * @param _CosmeticRule $candidate The candidate rule that might cover it.
     * @param string $domain The domain context being evaluated.
     * @param array<string, bool> $ghideExceptions Domains where generic hiding is disabled.
     */
    private function isCovered(array $rule, array $candidate, string $domain, array $ghideExceptions): bool
    {
        // =================================================================
        // Domain matching
        // =================================================================
        if ($candidate['domains'] !== []) {
            // A rule with a mix of inclusions and exclusions should not cover other rules
            // unless they have the exact same domain set.
            if ($candidate['hasMixedDomains']) {
                if ($candidate['domains'] !== $rule['domains']) {
                    return false;
                }
            } else {
                // Determine if the domain context is covered by the candidate.
                $isExplicitMatch = isset($candidate['domains'][$domain])
                    || DomainCoverage::findCovering($domain, $candidate['domains']) !== null;

                // Almost-global rules (only exclusions) implicitly cover any
                // non-negated domain that is not explicitly excluded.
                $isAlmostGlobalMatch = $candidate['isAlmostGlobal']
                    && $domain !== ''
                    && $domain[0] !== '~'
                    && !isset($candidate['domains']['~'.$domain]);

                if (!$isExplicitMatch && !$isAlmostGlobalMatch) {
                    return false;
                }
            }
        } elseif ($domain !== '' && isset($ghideExceptions[$domain])) {
            // Global rule $candidate does NOT cover domain if generic hiding is disabled for it.
            return false;
        }

        // =================================================================
        // Selector matching
        // =================================================================

        // Both rules are simple selectors
        if ($rule['parsedSimple'] !== null && $candidate['parsedSimple'] !== null) {
            return $this->isSimpleSelectorCovered($rule['parsedSimple'], $candidate['parsedSimple']);
        }

        // Both rules are attribute selectors
        if ($rule['attrData'] !== null && $candidate['attrData'] !== null) {
            return $this->isAttrCoveredBy($rule['attrData'], $candidate['attrData']);
        }

        // Fallback: if either is unparsed, assume false (unless selector identical)
        if ($rule['selector'] === $candidate['selector']) {
            return true;
        }

        // Standard (non-attribute) selector that cannot be parsed as a simple selector
        // can only be covered if identical (already handled above)
        return false;
    }

    /**
     * Determines whether a candidate rule is "better" (more general or earlier)
     * than the current best candidate.
     *
     * The comparison is performed in three stages:
     * 1. Selector generality — a selector that covers a broader set of elements
     *    is preferred.
     * 2. Domain generality — a rule with a broader domain scope is preferred.
     * 3. Line number — when all else is equal, the earlier rule is preferred
     *    as the canonical reference.
     *
     * @param _CosmeticRule $candidate The rule to evaluate.
     * @param _CosmeticRule $best The current best rule to compare against.
     */
    private function isBetter(array $candidate, array $best): bool
    {
        // 1. Selector generality
        if ($candidate['parsedSimple'] !== null && $best['parsedSimple'] !== null) {
            $candCoversBest = $this->isSimpleSelectorCovered($best['parsedSimple'], $candidate['parsedSimple']);
            $bestCoversCand = $this->isSimpleSelectorCovered($candidate['parsedSimple'], $best['parsedSimple']);

            // candidate is strictly more general
            if ($candCoversBest && !$bestCoversCand) {
                return true;
            }

            // best is strictly more general
            if (!$candCoversBest && $bestCoversCand) {
                return false;
            }
        } elseif ($candidate['attrData'] && $best['attrData']) {
            $candCoversBest = $this->isAttrCoveredBy($best['attrData'], $candidate['attrData']);
            $bestCoversCand = $this->isAttrCoveredBy($candidate['attrData'], $best['attrData']);

            // candidate is strictly more general
            if ($candCoversBest && !$bestCoversCand) {
                return true;
            }

            // best is strictly more general
            if (!$candCoversBest && $bestCoversCand) {
                return false;
            }
        }

        // 2. Domain generality
        $candCoversBest = DomainCoverage::coversRuleDomains($candidate['domains'], $best['domains'], $candidate['lineNum'] > $best['lineNum']);
        $bestCoversCand = DomainCoverage::coversRuleDomains($best['domains'], $candidate['domains'], $best['lineNum'] > $candidate['lineNum']);
        if ($candCoversBest !== $bestCoversCand) {
            return $candCoversBest;
        }

        // 3. Line number
        return $candidate['lineNum'] < $best['lineNum'];
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
     * - [href="abc"]    is covered by [href*="a"]
     * - [href^="https"] is covered by [href*="http"]
     *
     * @param _ParsedAttrSelector $rule The rule being checked.
     * @param _ParsedAttrSelector $candidate The candidate rule that might cover it.
     */
    private function isAttrCoveredBy(array $rule, array $candidate): bool
    {
        // $candidate covers $rule if $candidate has no tag (global) or same tag as $rule.
        if ($candidate['tag'] !== '' && $rule['tag'] !== $candidate['tag']) {
            return false;
        }

        // If $rule is case-insensitive but $candidate is case-sensitive, $candidate cannot cover $rule.
        if ($rule['modifier'] === 'i' && $candidate['modifier'] === '') {
            return false;
        }

        // Determine whether to compare values case-insensitively.
        $caseInsensitive = $candidate['modifier'] === 'i';
        $valR = $caseInsensitive ? strtolower($rule['value']) : $rule['value'];
        $valC = $caseInsensitive ? strtolower($candidate['value']) : $candidate['value'];

        // Exact match of operator and value.
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
        // Covers $rule only if $rule is "=" AND $candidate's value is an exact
        // word inside $rule's value.
        if ($candidate['operator'] === '~=' && $rule['operator'] === '=') {
            $words = preg_split('/\s+/', $valR);

            return in_array($valC, $words, true);
        }

        return false;
    }

    /**
     * Determine if the domain list contains both inclusions and exclusions.
     *
     * @param array<string, bool> $domains
     */
    private function isMixedDomains(array $domains): bool
    {
        $keys = array_keys($domains);
        $hasEx = array_any($keys, fn($d) => str_starts_with($d, '~'));
        $hasIn = array_any($keys, fn($d) => !str_starts_with($d, '~'));

        return $hasIn && $hasEx;
    }

    /**
     * Parses a comma-separated domain string into a normalized set.
     *
     * @param string $domainStr Comma-separated domain string.
     * @return array<string, bool> Associative array with domain as key and true as value.
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
     * Extracts domains from a exception rule.
     *
     * @param string $line The line to parse.
     * @return list<string> Returns a list of domains, or an empty list if the
     *                      line is not a ghide/ehide exception.
     */
    private function parseDomainExceptRuleOpt(string $line): array
    {
        if (!str_starts_with($line, '@@')) {
            return [];
        }

        $opts = ['ghide', 'generichide', 'ehide', 'elemhide'];

        // Form 1: @@||example.com^$ghide
        $ghideRegex = sprintf(
            '/^@@\|\|([a-z0-9.-]+)\^?\$(?:%s)(?:,|$)/i',
            implode('|', $opts),
        );
        if (preg_match($ghideRegex, $line, $m)) {
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

                if (in_array($opt, $opts, true)) {
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
     * Parses a simple attribute selector.
     *
     * @param string $selector The CSS selector to parse.
     * @return _ParsedAttrSelector|null Parsed data, or null if the selector
     *                                  does not match any supported form.
     */
    private function parseAttributeSelector(string $selector): ?array
    {
        // Explicit attribute selector: tag[attr op "value" mod?]
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
     * Builds a bucket key for attribute-selector grouping.
     *
     * @param string $type Bucket type (e.g. 'E', 'P').
     * @param string $separator The cosmetic separator (##, #@#, etc.).
     * @param string $tag The tag qualifier (empty for global).
     * @param string $attr The attribute name.
     * @param string|null $op The operator (required for partial keys).
     * @param string|null $val The attribute value.
     */
    private function buildAttrKey(
        string $type, string $separator,
        string $tag, string $attr, ?string $op = null, ?string $val = null,
    ): string {
        // Global
        if (str_starts_with($type, 'G|')) {
            $tag = '';
        }

        // Exact
        if ($type === 'E' || $type === 'G|E') {
            return "A|E|{$separator}|{$tag}|{$attr}|{$val}";
        }

        // Partial
        $type = 'A|P|'.$op;
        $limit = self::ATTR_PARTIAL_KEY_LEN;
        // For URLs, extend the truncation limit to include the protocol and
        // optional www prefix, since those are highly discriminative.
        if ($op === '^=' && preg_match('/^https?:\/\/(?:www\.)?/', $val, $m)) {
            $limit = strlen($m[0]) + $limit;
        }

        return match ($op) {
            '^=' => "{$type}|".mb_substr($val, 0, $limit)."|{$separator}|{$tag}|{$attr}",
            '$=' => "{$type}|".mb_substr($val, -$limit)."|{$separator}|{$tag}|{$attr}",
            default => "{$type}|{$separator}|{$tag}|{$attr}",
        };
    }

    // =========================================================================
    // Simple selector utilities
    // =========================================================================

    /**
     * Parses a CSS selector into its simple components (tag, #id, .classes).
     *
     * Examples of parseable selectors:
     * - div        => tag=div, id='', classes=[]
     * - .ad.banner => tag='', id='', classes=['ad','banner']
     * - div#ad.x.y => tag=div, id='ad', classes=['x','y']
     *
     * Examples of unparseable selectors (returns null):
     * - div > span  (combinator)
     * - .ad:hover   (pseudo-class)
     * - div[data-x] (attribute selector)
     *
     * @param string $selector The CSS selector to parse.
     * @return _ParsedSimpleSelector|null Parsed components with classes sorted
     *                                    alphabetically, or null if the selector
     *                                    contains unsupported constructs.
     */
    private function parseSimpleSelector(string $selector): ?array
    {
        $tag = '';
        $id = '';
        $classes = [];

        // Extract tag (at start)
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*/', $selector, $m)) {
            $tag = $m[0];
            $selector = substr($selector, strlen($tag));
        }

        // Extract the #id component
        if (preg_match('/#([a-zA-Z0-9_-]+)/', $selector, $m)) {
            $id = $m[1];
            $selector = str_replace($m[0], '', $selector);
        }

        // Extract all .class components.
        //
        // The regex handles backslash-escaped characters within class names
        // (e.g. `.foo\.bar` is a single class "foo.bar"). Each match starts
        // with a literal dot followed by one or more characters that are
        // neither dots nor unescaped special characters.
        //
        // Pattern breakdown:
        //   \.                  — literal dot (class prefix)
        //   (?:                 — one or more of:
        //     (?![.])[^.]       —   a non-dot character, OR
        //     |\\\\.            —   a backslash followed by any character
        //   )+                  —   (escape sequence)
        preg_match_all('/\.(?:(?![.])[^.]|\\\\.)+/', $selector, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $match) {
                // Strip the leading dot to get the bare class name
                $classes[] = substr($match, 1);
            }

            // Remove all matched class tokens from the selector so we can
            // verify that nothing meaningful remains.
            $selector = preg_replace('/\.(?:(?![.])[^.]|\\\\.)+/', '', $selector);
        }

        // After extracting all recognized components, any remaining non-empty
        // content indicates an unsupported construct (combinator, pseudo-class,
        // attribute selector, etc.). Bail out in that case.
        if (trim($selector) !== '') {
            return null;
        }

        // Sort classes for normalization. This ensures that `.a.b` and `.b.a`
        // are treated as structurally identical.
        sort($classes);

        return ['tag' => $tag, 'id' => $id, 'classes' => $classes];
    }

    /**
     * Gets the canonical (normalized) string form of a selector.
     *
     * Canonicalization reorders class tokens alphabetically and reconstructs
     * the selector in a deterministic order: tag -> #id -> .classes. This is used
     * as the bucket key in the interaction map so that semantically identical
     * selectors written in different class orders resolve to the same bucket.
     *
     * @param string $selector The original selector string.
     * @param _ParsedSimpleSelector|null $parsed Pre-parsed data (avoids redundant parsing
     *                                           when already available).
     * @return string The canonical form of the selector. If the selector cannot be parsed
     *                as a simple selector, it is returned unchanged.
     */
    private function getCanonicalSelector(string $selector, ?array $parsed): string
    {
        if ($parsed === null) {
            return $selector;
        }

        $canonical = '';
        if ($parsed['tag'] !== '') {
            $canonical .= $parsed['tag'];
        }
        if ($parsed['id'] !== '') {
            $canonical .= '#'.$parsed['id'];
        }

        // Classes are already sorted by parseSimpleSelector(), producing a
        // deterministic order.
        foreach ($parsed['classes'] as $cls) {
            $canonical .= '.'.$cls;
        }

        return $canonical;
    }

    /**
     * Determines whether a specific simple selector is covered by a more
     * general one.
     *
     * Examples:
     * - `.ad` covers `.ad.banner` (subset of classes)
     * - `div.ad` covers `div.ad.banner` (same tag, subset of classes)
     * - `span.ad` does NOT cover `div.ad.banner` (different tag)
     * - `#main` covers `#main.widget` (same ID, subset of classes)
     *
     * @param _ParsedSimpleSelector $specific The more specific selector.
     * @param _ParsedSimpleSelector $general The potentially covering selector.
     * @return bool True if $general covers $specific.
     */
    private function isSimpleSelectorCovered(array $specific, array $general): bool
    {
        // Tag: general can be empty (universal) or must match
        if ($general['tag'] !== '' && $general['tag'] !== $specific['tag']) {
            return false;
        }

        // ID: general can be empty or must match
        if ($general['id'] !== '' && $general['id'] !== $specific['id']) {
            return false;
        }

        // Classes: all general classes must be present in specific
        $specificClasses = array_fill_keys($specific['classes'], true);
        foreach ($general['classes'] as $cls) {
            if (!isset($specificClasses[$cls])) {
                return false;
            }
        }

        return true;
    }
}
