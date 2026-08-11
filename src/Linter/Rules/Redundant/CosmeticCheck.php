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
 *  hasMixedDomains: bool,
 *  isAlmostGlobal: bool,
 *  conditionKey: string,
 * }
 */
final class CosmeticCheck implements Rule
{
    private const ATTR_PARTIAL_KEY_LEN = 2;

    /** @var array<string, int> */
    private array $exactSeen = [];

    /** @var list<_CosmeticRule> */
    private array $collection = [];

    /** @var array<string, list<int>> */
    private array $interactionMap = [];

    /** @var array<string, bool> */
    private array $ghideExceptions = [];

    /** @var list<int> */
    private array $simpleSelectorIndices = [];

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

        // Pass 1: Parsing and Collection
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

            // Attempt both parsing strategies.
            $attrData = $this->parseAttributeSelector($selector);
            $parsedSimple = $this->parseSimpleSelector($selector);

            // Pre-calculate domain status to optimize the isCovered() hot path.
            $isMixed = $this->isMixedDomains($domains);
            $isAlmostGlobal = false;
            if (!$isMixed && $domains !== []) {
                // An "almost global" rule contains only exclusions (negated domains).
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
                'hasMixedDomains' => $isMixed,
                'isAlmostGlobal' => $isAlmostGlobal,
                'conditionKey' => $conditionKey,
            ];

            $this->collection[$lineNum] = $entry;

            // Register this rule in the simple-selector index so that
            // findCandidates() can locate subset/superset relationships.
            if ($parsedSimple !== null) {
                $this->simpleSelectorIndices[] = $lineNum;
            }

            // Group rules into buckets
            if ($attrData && !str_starts_with($selector, '.') && !str_starts_with($selector, '#')) {
                $val = strtolower($attrData['value']);
                $op = $attrData['operator'];
                $tag = $attrData['tag'];
                $attr = $attrData['attr'];

                if (in_array($op, ['^=', '$=', '*='], true)) {
                    $partialKey = $this->buildAttrKey('P', $separator, $tag, $attr, $op, $val);
                    $this->interactionMap[$partialKey][] = $lineNum;
                } else {
                    $exactKey = $this->buildAttrKey('E', $separator, $tag, $attr, $op, $val);
                    $this->interactionMap[$exactKey][] = $lineNum;
                }
            } else {
                $canonical = $this->getCanonicalSelector($selector, $parsedSimple);
                $this->interactionMap['S|'.$separator.$canonical][] = $lineNum;
            }
        }

        // Pass 2: Redundancy Analysis
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
        $this->simpleSelectorIndices = [];
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

        $canonicalSelector = $this->getCanonicalSelector($entry['selector'], $entry['parsedSimple']);
        $domainStr = implode(',', array_keys($entry['domains']));
        $key = $domainStr.'|'.$entry['separator'].'|'.$canonicalSelector.'|'.$entry['conditionKey'];

        if (isset($this->exactSeen[$key])) {
            $err->message(sprintf(
                'Redundant filter: %s already defined on line %d.',
                $line, $this->exactSeen[$key],
            ))->line($entry['lineNum'])->build();

            return true;
        }

        $this->exactSeen[$key] = $entry['lineNum'];

        return false;
    }

    /**
     * Checks whether the entire rule is made redundant by a global or broader rule.
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

            // Track the "best" (most general) parent.
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
                    'Redundant filter: %s already covered by %s on line %d.',
                    $content,
                    $bestParent['separator'].$bestParent['selector'],
                    $bestParent['lineNum'],
                );
            } else {
                $message = sprintf(
                    'Redundant filter: %s is redundant due to more general selector on line %d.',
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
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param _CosmeticRule $entry
     */
    private function checkDomainRedundancy($err, array $entry): void
    {
        $domains = array_keys($entry['domains']);

        // Phase 1: Internal coverage
        $internallyCoveredDomains = [];

        foreach (DomainCoverage::findCovered($domains) as $domain => $coveringDomain) {
            $internallyCoveredDomains[] = $domain;
            $err->message(sprintf(
                'Redundant filter: domain %s is covered by "%s".',
                $domain, $coveringDomain,
            ))->line($entry['lineNum'])->build();
        }

        // Phase 2: External coverage
        $candidates = $this->findCandidates($entry, $this->interactionMap);
        $coverageMap = [];
        $parentMap = [];

        foreach ($entry['domains'] as $domain => $_) {
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
                        'Redundant filter: domain %s already covered on line %d.',
                        $domain, $parent['lineNum'],
                    );
                } else {
                    $message = sprintf(
                        'Redundant filter: domain %s in %s already covered on line %d.',
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
     * @param _CosmeticRule $entry
     * @param array<string, list<int>> $interactionMap
     * @return list<int>
     */
    private function findCandidates(array $entry, array $interactionMap): array
    {
        $candidates = [];
        $separator = $entry['separator'];

        // Attribute selector candidates (only for explicit attribute selectors)
        if ($entry['attrData'] && !str_starts_with($entry['selector'], '.') && !str_starts_with($entry['selector'], '#')) {
            $val = strtolower($entry['attrData']['value']);
            $op = $entry['attrData']['operator'];
            $tag = $entry['attrData']['tag'];
            $attr = $entry['attrData']['attr'];

            // Exact candidates
            $exactKey = $this->buildAttrKey('E', $separator, $tag, $attr, val: $val);
            if (isset($interactionMap[$exactKey])) {
                array_push($candidates, ...$interactionMap[$exactKey]);
            }

            // Word candidates
            $words = $op === '=' ? (preg_split('/\s+/', $val) ?: []) : [];
            if ($op === '=') {
                foreach ($words as $word) {
                    if ($word === '' || $word === $val) {
                        continue;
                    }

                    $wordKey = $this->buildAttrKey('E', $separator, $tag, $attr, val: $word);
                    if (isset($interactionMap[$wordKey])) {
                        array_push($candidates, ...$interactionMap[$wordKey]);
                    }
                }
            }

            // Partial candidates
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

            // Global candidates
            if ($tag !== '') {
                $geKey = $this->buildAttrKey('G|E', $separator, $tag, $attr, val: $val);
                if (isset($interactionMap[$geKey])) {
                    array_push($candidates, ...$interactionMap[$geKey]);
                }

                if ($op === '=') {
                    foreach ($words as $word) {
                        if ($word === '' || $word === $val) {
                            continue;
                        }

                        $globalWordKey = $this->buildAttrKey('G|E', $separator, $tag, $attr, val: $word);
                        if (isset($interactionMap[$globalWordKey])) {
                            array_push($candidates, ...$interactionMap[$globalWordKey]);
                        }
                    }
                }

                foreach ($targetOps as $tOp) {
                    $gpKey = $this->buildAttrKey('G|P', $separator, $tag, $attr, $tOp, $val);
                    if (isset($interactionMap[$gpKey])) {
                        array_push($candidates, ...$interactionMap[$gpKey]);
                    }
                }
            }
        }

        // Standard / Simple selector candidates
        $selector = $entry['selector'];
        $parsed = $entry['parsedSimple'];
        $canonical = $this->getCanonicalSelector($selector, $parsed);
        $key = 'S|'.$separator.$canonical;

        if (isset($interactionMap[$key])) {
            $candidates = array_merge($candidates, $interactionMap[$key]);
        }

        // Structural simple selector scan (tag, id, class subsets)
        if ($parsed !== null) {
            foreach ($this->simpleSelectorIndices as $idx) {
                if ($idx === $entry['lineNum']) {
                    continue;
                }

                $cand = $this->collection[$idx];

                if ($cand['separator'] !== $separator) {
                    continue;
                }

                $candParsed = $cand['parsedSimple'];
                if ($candParsed === null) {
                    continue;
                }

                // Check if candidate can cover this entry or vice versa
                if ($this->isSimpleSelectorCovered($parsed, $candParsed) || $this->isSimpleSelectorCovered($candParsed, $parsed)) {
                    $candidates[] = $idx;
                }
            }
        }

        $candidates = array_unique($candidates);

        return array_values(array_filter($candidates, function ($idx) use ($entry) {
            return $this->collection[$idx]['conditionKey'] === $entry['conditionKey'];
        }));
    }

    /**
     * Determines whether a cosmetic rule is covered by a candidate rule for a specific domain.
     *
     * @param _CosmeticRule $rule
     * @param _CosmeticRule $candidate
     * @param string $domain
     * @param array<string, bool> $ghideExceptions
     */
    private function isCovered(array $rule, array $candidate, string $domain, array $ghideExceptions): bool
    {
        // Domain matching
        if ($candidate['domains'] !== []) {
            if ($candidate['hasMixedDomains']) {
                if ($candidate['domains'] !== $rule['domains']) {
                    return false;
                }
            } else {
                $isExplicitMatch = isset($candidate['domains'][$domain])
                    || DomainCoverage::findCovering($domain, $candidate['domains']) !== null;
                $isAlmostGlobalMatch = $candidate['isAlmostGlobal']
                    && $domain !== ''
                    && $domain[0] !== '~'
                    && !isset($candidate['domains']['~'.$domain]);

                if (!$isExplicitMatch && !$isAlmostGlobalMatch) {
                    return false;
                }
            }
        } elseif ($domain !== '' && isset($ghideExceptions[$domain])) {
            return false;
        }

        // Selector matching
        $ruleParsed = $rule['parsedSimple'];
        $candParsed = $candidate['parsedSimple'];

        if ($ruleParsed !== null && $candParsed !== null) {
            return $this->isSimpleSelectorCovered($ruleParsed, $candParsed);
        }

        if ($rule['attrData'] !== null && $candidate['attrData'] !== null) {
            return $this->isAttrCoveredBy($rule['attrData'], $candidate['attrData']);
        }

        return $rule['selector'] === $candidate['selector'];
    }

    /**
     * Determines whether a candidate rule is "better" (more general or earlier).
     *
     * @param _CosmeticRule $candidate
     * @param _CosmeticRule $best
     */
    private function isBetter(array $candidate, array $best): bool
    {
        // 1. Selector generality
        $candParsed = $candidate['parsedSimple'];
        $bestParsed = $best['parsedSimple'];

        if ($candParsed !== null && $bestParsed !== null) {
            $candCoversBest = $this->isSimpleSelectorCovered($bestParsed, $candParsed);
            $bestCoversCand = $this->isSimpleSelectorCovered($candParsed, $bestParsed);

            if ($candCoversBest && !$bestCoversCand) {
                return true;
            }

            if (!$candCoversBest && $bestCoversCand) {
                return false;
            }
        } elseif ($candidate['attrData'] && $best['attrData']) {
            $candCoversBest = $this->isAttrCoveredBy($best['attrData'], $candidate['attrData']);
            $bestCoversCand = $this->isAttrCoveredBy($candidate['attrData'], $best['attrData']);

            if ($candCoversBest && !$bestCoversCand) {
                return true;
            }

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
     * @param _ParsedAttrSelector $rule
     * @param _ParsedAttrSelector $candidate
     */
    private function isAttrCoveredBy(array $rule, array $candidate): bool
    {
        if ($candidate['tag'] !== '' && $rule['tag'] !== $candidate['tag']) {
            return false;
        }

        if ($rule['modifier'] === 'i' && $candidate['modifier'] === '') {
            return false;
        }

        $caseInsensitive = $candidate['modifier'] === 'i';
        $valR = $caseInsensitive ? strtolower($rule['value']) : $rule['value'];
        $valC = $caseInsensitive ? strtolower($candidate['value']) : $candidate['value'];

        if ($rule['operator'] === $candidate['operator'] && $valR === $valC) {
            return true;
        }

        if ($candidate['operator'] === '*=') {
            return str_contains($valR, $valC);
        }

        if ($candidate['operator'] === '^=') {
            return ($rule['operator'] === '=' || $rule['operator'] === '^=')
                && str_starts_with($valR, $valC);
        }

        if ($candidate['operator'] === '$=') {
            return ($rule['operator'] === '=' || $rule['operator'] === '$=')
                && str_ends_with($valR, $valC);
        }

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
     * @param string $domainStr
     * @return array<string, bool>
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
     * Extracts domains from an exception rule.
     *
     * @param string $line
     * @return list<string>
     */
    private function parseDomainExceptRuleOpt(string $line): array
    {
        if (!str_starts_with($line, '@@')) {
            return [];
        }

        $opts = ['ghide', 'generichide', 'ehide', 'elemhide'];

        $ghideRegex = sprintf(
            '/^@@\|\|([a-z0-9.-]+)\^?\$(?:%s)(?:,|$)/i',
            implode('|', $opts),
        );
        if (preg_match($ghideRegex, $line, $m)) {
            return [strtolower($m[1])];
        }

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
     * @param string $selector
     * @return _ParsedAttrSelector|null
     */
    private function parseAttributeSelector(string $selector): ?array
    {
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

        if (preg_match('/^(?:(?<tag>[a-z0-9_-]+))?\.(?<val>[a-z0-9_-]+)$/i', $selector, $m)) {
            return [
                'tag' => strtolower($m['tag']),
                'attr' => 'class',
                'operator' => '~=',
                'value' => $m['val'],
                'modifier' => '',
            ];
        }

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
     */
    private function buildAttrKey(
        string $type, string $separator,
        string $tag, string $attr, ?string $op = null, ?string $val = null,
    ): string {
        if (str_starts_with($type, 'G|')) {
            $tag = '';
        }

        if ($type === 'E' || $type === 'G|E') {
            return "A|E|{$separator}|{$tag}|{$attr}|{$val}";
        }

        $type = 'A|P|'.$op;
        $limit = self::ATTR_PARTIAL_KEY_LEN;
        if ($op === '^=' && preg_match('/^https?:\/\/(?:www\.)?/', $val, $m)) {
            $limit = strlen($m[0]) + $limit;
        }

        return match ($op) {
            '^=' => "{$type}|".mb_substr($val, 0, $limit)."|{$separator}|{$tag}|{$attr}",
            '$=' => "{$type}|".mb_substr($val, -$limit)."|{$separator}|{$tag}|{$attr}",
            default => "{$type}|{$separator}|{$tag}|{$attr}",
        };
    }

    /**
     * Parses a CSS selector into simple components (tag, #id, .classes).
     *
     * @param string $selector
     * @return _ParsedSimpleSelector|null
     */
    private function parseSimpleSelector(string $selector): ?array
    {
        $tag = '';
        $id = '';
        $classes = [];

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*/', $selector, $m)) {
            $tag = $m[0];
            $selector = substr($selector, strlen($tag));
        }

        if (preg_match('/#([a-zA-Z0-9_-]+)/', $selector, $m)) {
            $id = $m[1];
            $selector = str_replace($m[0], '', $selector);
        }

        preg_match_all('/\.(?:(?![.])[^.]|\\\\.)+/', $selector, $matches);

        if (!empty($matches[0])) {
            foreach ($matches[0] as $match) {
                $classes[] = substr($match, 1);
            }

            $selector = preg_replace('/\.(?:(?![.])[^.]|\\\\.)+/', '', $selector);
        }

        if (trim($selector) !== '') {
            return null;
        }

        sort($classes);

        return ['tag' => $tag, 'id' => $id, 'classes' => $classes];
    }

    /**
     * Returns the canonical (normalized) string form of a selector.
     *
     * @param string $selector
     * @param _ParsedSimpleSelector|null $parsed
     * @return string
     */
    private function getCanonicalSelector(string $selector, ?array $parsed = null): string
    {
        if ($parsed === null) {
            $parsed = $this->parseSimpleSelector($selector);
        }

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

        foreach ($parsed['classes'] as $cls) {
            $canonical .= '.'.$cls;
        }

        return $canonical;
    }

    /**
     * Determines whether a specific simple selector is covered by a more general one.
     *
     * @param _ParsedSimpleSelector $specific
     * @param _ParsedSimpleSelector $general
     * @return bool
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
