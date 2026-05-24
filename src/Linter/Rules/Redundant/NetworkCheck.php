<?php

namespace Realodix\Haiku\Linter\Rules\Redundant;

use Illuminate\Support\Str;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Linter\Util;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 * @phpstan-type _NetRule array{
 *  lineNum: int,
 *  line: string,
 *  type: string,
 *  pattern: string,
 *  options: list<string>,
 *  optionsKey: string,
 *  domains: list<array{name: string, type: string}>,
 *  hasOptions: bool,
 *  hasDomains: bool,
 *  hasMatchCase: bool,
 *  hasMixedDomains: bool,
 *  isAlmostGlobal: bool,
 *  regex: string,
 * }
 */
final class NetworkCheck implements Rule
{
    private const TYPE_BLACKLIST = 'blacklist';
    private const TYPE_WHITELIST = 'whitelist';

    /**
     * Exact duplicates
     *
     * @var array{
     *   exact: array<string, int>,
     *   pattern_options: array<string, array<string, array<string, array<string, int>>>>,
     * }
     */
    private array $seen;

    /**
     * Global redundancy checking
     *
     * @var array{
     *   by_token: array<string, array<string, list<_NetRule>>>,
     *   no_token: array<string, list<_NetRule>>,
     *   stored: array<string, array<string, bool>>,
     * }
     */
    private array $globalIndex;

    /**
     * @var array<string, string>
     */
    private array $regexCache = [];

    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        if (!$this->config->rules['no_dupe_rules']) {
            return [];
        }

        $err = new RuleErrorBuilder;
        /** @var list<_NetRule> */
        $collection = [];

        // Pass 1: Parse and collect state
        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if ($this->shouldSkip($line)) {
                continue;
            }

            $type = str_starts_with($line, '@@') ? self::TYPE_WHITELIST : self::TYPE_BLACKLIST;
            $hasOpts = (bool) preg_match(Regex::NET_OPTION, $line, $m);
            $optStr = $hasOpts ? $m[2] : '';
            $opts = $hasOpts ? Util::splitOptions($optStr) : [];
            $hasMatchCase = $this->hasOption($opts, 'match-case');

            $pattern = $hasOpts ? $m[1] : $line;
            if (!$hasMatchCase) {
                $pattern = strtolower($pattern);
            }

            $nonDomainOpts = $this->extractNonDomainOptions($opts, $hasMatchCase);
            $domains = $this->parseDomains($opts);
            sort($nonDomainOpts);
            $optionsKey = implode(',', $nonDomainOpts);
            $hasMixedDomains = $this->isMixedDomains($domains);
            $isAlmostGlobal = false;
            if (!$hasMixedDomains && $domains !== []) {
                $isAlmostGlobal = str_starts_with($domains[0]['name'], '~');
            }

            $entry = [
                'lineNum' => $lineNum,
                'line' => $line,
                'type' => $type,
                'pattern' => $pattern,
                'options' => $opts,
                'optionsKey' => $optionsKey,
                'domains' => $domains,
                'hasOptions' => $hasOpts,
                'hasDomains' => !empty($domains),
                'hasMatchCase' => $hasMatchCase,
                'hasMixedDomains' => $hasMixedDomains,
                'isAlmostGlobal' => $isAlmostGlobal,
            ];
            $collection[$lineNum] = $entry;

            if ($hasOpts) {
                $seenMap = &$this->seen['pattern_options'][$type][$pattern][$optionsKey];
                foreach ($domains as $d) {
                    $entityKey = $d['type'].':'.$d['name'];
                    if (!isset($seenMap[$entityKey])) {
                        $seenMap[$entityKey] = $lineNum;
                    }
                }
            }

            // A rule is considered "specific" only if it contains only an inclusion list and has no negated domain.
            $isSpecific = ($domains !== [] && !str_starts_with($domains[0]['name'], '~')) && !$hasMixedDomains;
            if (!$isSpecific) {
                $uniqueKey = $pattern.'::'.$optionsKey.'::'.implode(',', array_column($domains, 'name'));
                if (!isset($this->globalIndex['stored'][$type][$uniqueKey])) {
                    $this->globalIndex['stored'][$type][$uniqueKey] = true;
                    $token = $this->getPrimaryToken($pattern);
                    if ($token !== null) {
                        $this->globalIndex['by_token'][$type][$token][] = $entry;
                    } else {
                        $this->globalIndex['no_token'][$type][] = $entry;
                    }
                }
            }
        }

        // Pass 2: Check redundancy
        foreach ($collection as $entry) {
            // 1. Exact duplicate check (checks if the exact same line was already seen)
            if ($this->checkExactDuplicate($err, $entry)) {
                continue;
            }

            // 2. Redundancy check against global rules (no domains)
            // This covers both purely generic rules and rules with matching options.
            if ($this->checkGlobalRedundancy($err, $entry)) {
                continue;
            }

            // 3. Domain level redundancy (only for rules that specify domains)
            if ($entry['hasDomains']) {
                $this->checkDomainRedundancy($err, $entry);
            }
        }

        $this->reset();

        return $err->toArray();
    }

    private function reset(): void
    {
        $this->seen = [
            'exact' => [],
            'pattern_options' => [self::TYPE_BLACKLIST => [], self::TYPE_WHITELIST => []],
        ];

        $this->globalIndex = [
            'by_token' => [self::TYPE_BLACKLIST => [], self::TYPE_WHITELIST => []],
            'no_token' => [self::TYPE_BLACKLIST => [], self::TYPE_WHITELIST => []],
            // For global rules deduplication
            'stored' => [self::TYPE_BLACKLIST => [], self::TYPE_WHITELIST => []],
        ];

        $this->regexCache = [];
    }

    private function shouldSkip(string $line): bool
    {
        if (Util::isCommentOrEmpty($line) || str_starts_with($line, '[$')) {
            return true;
        }

        return (bool) preg_match(Regex::IS_COSMETIC_RULE, $line);
    }

    /**
     * @param _NetRule $entry
     */
    private function checkExactDuplicate(RuleErrorBuilder $err, array $entry): bool
    {
        $line = $entry['line'];
        $exactKey = $entry['hasMatchCase'] ? $line : strtolower($line);
        if (isset($this->seen['exact'][$exactKey])) {
            $err->message(sprintf(
                'Redundant filter: %s already defined on line %d.',
                $line, $this->seen['exact'][$exactKey],
            ))->line($entry['lineNum'])->build();

            return true;
        }

        $this->seen['exact'][$exactKey] = $entry['lineNum'];

        return false;
    }

    /**
     * @param _NetRule $entry
     */
    private function checkGlobalRedundancy(RuleErrorBuilder $err, array $entry): bool
    {
        $pattern = $entry['pattern'];
        $opts = $entry['options'];
        $type = $entry['type'];

        /** @var _NetRule|null */
        $best = null;

        $bucketsToCheck = [];
        $tokens = $this->getAllTokens($pattern);
        foreach ($tokens as $token) {
            if (isset($this->globalIndex['by_token'][$type][$token])) {
                $bucketsToCheck[] = $this->globalIndex['by_token'][$type][$token];
            }
        }
        if (!empty($this->globalIndex['no_token'][$type])) {
            $bucketsToCheck[] = $this->globalIndex['no_token'][$type];
        }

        foreach ($bucketsToCheck as $bucket) {
            foreach ($bucket as $candidate) {
                if ($entry['lineNum'] === $candidate['lineNum']) {
                    continue;
                }

                if ($candidate['optionsKey'] !== '') {
                    if ($entry['optionsKey'] === '' || $candidate['optionsKey'] !== $entry['optionsKey']) {
                        continue;
                    }
                }

                if (!$this->isCovered($entry, $candidate)) {
                    continue;
                }

                if (!$entry['hasOptions'] && !$entry['hasDomains'] && $entry['lineNum'] < $candidate['lineNum']) {
                    if (preg_match($this->buildRegex($entry['pattern']), $candidate['pattern'])) {
                        continue;
                    }
                }

                if ($best === null || $this->isBetter($candidate, $best)) {
                    $best = $candidate;
                }
            }
        }

        if ($best !== null) {
            // ||example.com and |example.com are not redundant
            if (str_starts_with($entry['pattern'], '|') && str_starts_with($best['pattern'], '|')
                && strspn($entry['pattern'], '|') !== strspn($best['pattern'], '|')
            ) {
                return false;
            }

            // The special case of options that avoids redundancy
            if ($entry['hasOptions'] && $this->hasOption($opts, ['badfilter', 'popup'])) {
                return false;
            }
            // Exception options (e.g., $generichide) have distinct behaviors. That rule should not
            // be considered redundant by rules that do not have an options.
            $exceptionOpts = ['ghide', 'generichide', 'shide', 'specifichide', 'ehide', 'elemhide'];
            if ($this->hasOption($opts, $exceptionOpts) && !$best['hasOptions']) {
                return false;
            }

            // If patterns and options are identical, it's a direct duplicate
            if (!$entry['hasDomains']
                && $best['hasOptions'] === $entry['hasOptions']
                && $pattern === $best['pattern']
            ) {
                $err->message(sprintf(
                    'Redundant filter: %s already defined on line %d.',
                    $entry['line'], $best['lineNum'],
                ))->line($entry['lineNum'])->build();

                return true;
            }

            // Adjust message based on candidate type for domain-specific filters
            if ($entry['hasDomains'] && $best['hasOptions']) {
                $err->message(sprintf(
                    'Redundant filter: %s already covered by global filter on line %d.',
                    Str::limit($entry['line'], 80), $best['lineNum'],
                ))->line($entry['lineNum'])->build();

                return true;
            }

            $err->message(sprintf(
                'Redundant filter: %s already covered by %s on line %d.',
                Str::limit($entry['line'], 80), $best['pattern'], $best['lineNum'],
            ))->line($entry['lineNum'])->build();

            return true;
        }

        return false;
    }

    /**
     * @param _NetRule $entry
     */
    private function checkDomainRedundancy(RuleErrorBuilder $err, array $entry): void
    {
        $type = $entry['type'];
        $optionsKey = $entry['optionsKey'];
        $seenMap = &$this->seen['pattern_options'][$type][$entry['pattern']][$optionsKey];

        // The rule is DOMAIN-SPECIFIC and not covered by a GLOBAL rule.
        // Check if individual domains are redundant against previous domain-specific rules.
        $domainTypes = [];
        foreach ($entry['domains'] as $d) {
            $domainTypes[$d['type']] = true;
        }

        $isMixedContext = isset($domainTypes['domain'])
            && (isset($domainTypes['denyallow']) || isset($domainTypes['to']));

        $redundantDomains = [];
        foreach ($entry['domains'] as $d) {
            $entityKey = $d['type'].':'.$d['name'];
            if (isset($seenMap[$entityKey]) && $entry['lineNum'] > $seenMap[$entityKey]) {
                $redundantDomains[] = [
                    'domain' => $d['name'],
                    'atLineNum' => $seenMap[$entityKey],
                ];
            }
        }

        if (!$isMixedContext && !empty($redundantDomains)) {
            foreach ($redundantDomains as $rd) {
                $err->message(sprintf(
                    'Redundant filter: domain %s already covered on line %d.',
                    $rd['domain'], $rd['atLineNum'],
                ))->line($entry['lineNum'])->build();
            }
        }
    }

    /**
     * Determine if the current rule is semantically and domain-wise fully covered
     * by a candidate rule.
     *
     * A rule is considered covered if:
     * 1. The candidate's pattern is more general than (or encompasses) the current rule's pattern.
     * 2. The candidate's domain restrictions encompass all domains where the current rule applies.
     * 3. A candidate with mixed domains (both inclusions and exclusions) can ONLY cover
     *    a rule that features the exact same domain set.
     *
     * @param _NetRule $rule The rule being checked for redundancy.
     * @param _NetRule $candidate The candidate rule that might cover the target rule.
     */
    private function isCovered(array $rule, array $candidate): bool
    {
        if (!@preg_match($this->buildRegex($candidate['pattern']), $rule['pattern'])) {
            return false;
        }

        if ($candidate['domains'] !== []) {
            // A rule with a mix of inclusions and exclusions should not cover other rules
            // unless they have the exact same domain set.
            if ($candidate['hasMixedDomains']) {
                if ($candidate['domains'] !== $rule['domains']) {
                    return false;
                }
            }

            // Rule must be covered by candidate's domains
            $ruleDomains = $rule['domains'];
            if ($ruleDomains === []) {
                $ruleDomains = $this->extractDomainsFromPattern($rule['pattern']);
            }

            if ($ruleDomains === []) {
                $ruleDomains = [['name' => '', 'type' => 'domain']];
            }

            foreach ($ruleDomains as $d) {
                if (!$this->isDomainMatched($d['name'], $candidate)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Determine if the $candidate rule is "better" (more general or earlier)
     * than the current $best.
     *
     * @param _NetRule $candidate The rule to evaluate.
     * @param _NetRule $best The current best rule to compare against.
     */
    private function isBetter(array $candidate, array $best): bool
    {
        // 1. Semantic generality in Pattern
        $candCoversBest = preg_match($this->buildRegex($candidate['pattern']), $best['pattern']);
        $bestCoversCand = preg_match($this->buildRegex($best['pattern']), $candidate['pattern']);

        if ($candCoversBest && !$bestCoversCand) {
            return true; // $candidate is strictly more general
        }
        if (!$candCoversBest && $bestCoversCand) {
            return false; // $best is strictly more general
        }

        // 2. Options — no options is better (more general)
        if ($candidate['optionsKey'] === '' && $best['optionsKey'] !== '') {
            return true;
        }
        if ($candidate['optionsKey'] !== '' && $best['optionsKey'] === '') {
            return false;
        }

        // 3. Globalness (Global rules are better references than domain-specific ones)
        $candIsGlobal = empty($candidate['domains']);
        $bestIsGlobal = empty($best['domains']);
        if ($candIsGlobal && !$bestIsGlobal) {
            return true;
        }
        if (!$candIsGlobal && $bestIsGlobal) {
            return false;
        }

        // 4. Line order (Earlier rules are preferred as reference points)
        return $candidate['lineNum'] < $best['lineNum'];
    }

    /**
     * Check if a specific domain (or global context) is covered by a list of domain filters.
     *
     * A domain is matched if:
     * - It is explicitly present in the domain list.
     * - Or, the list contains ONLY exclusions and the domain is not among them.
     *
     * @param string $domain The domain to check (use empty string for global context).
     * @param _NetRule $rule The rule to check against.
     */
    private function isDomainMatched(string $domain, array $rule): bool
    {
        foreach ($rule['domains'] as $rd) {
            if ($rd['name'] === $domain) {
                return true;
            }
        }

        if (
            // A rule that specifies any domain restrictions (inclusions or exclusions)
            // cannot match the global context. An empty string represents the global domain,
            // so we explicitly reject it.
            $domain === ''
            // Domains prefixed with '~' denote exclusion filters (e.g., ~example.com).
            // Such exclusions should not be considered a match for coverage checks.
            || str_starts_with($domain, '~')
        ) {
            return false;
        }

        // Only Almost Global rules (exclusion-only) can cover domains implicitly.
        if ($rule['isAlmostGlobal']) {
            foreach ($rule['domains'] as $rd) {
                if ($rd['name'] === '~'.$domain) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Determine if the domain list contains both inclusions and exclusions.
     *
     * @param list<array{name: string, type: string}> $domains
     */
    private function isMixedDomains(array $domains): bool
    {
        $hasIn = false;
        $hasEx = false;

        foreach ($domains as $d) {
            if (str_starts_with($d['name'], '~')) {
                $hasEx = true;
            } else {
                $hasIn = true;
            }

            if ($hasIn && $hasEx) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts non-domain behavioral options (e.g. image, script) from the raw options list.
     *
     * @param list<string> $options The raw options list
     * @param bool $hasMatchCase Whether match-case is enabled
     * @return list<string> Non-domain options
     */
    private function extractNonDomainOptions(array $options, bool $hasMatchCase): array
    {
        $nonDomainOpts = [];
        $reDomainOpt = '/^('.implode('|', Registry::DOMAIN_OPTIONS).')=/i';

        foreach ($options as $opt) {
            $opt = trim($opt);
            if (!preg_match($reDomainOpt, $opt)) {
                $nonDomainOpts[] = $hasMatchCase ? $opt : strtolower($opt);
            }
        }

        return $nonDomainOpts;
    }

    /**
     * Parses domain-related options from the raw options list into structured objects.
     *
     * @param list<string> $options The raw options list
     * @return list<array{name: string, type: string}>
     */
    private function parseDomains(array $options): array
    {
        $domains = [];
        $reDomainOpt = '/^('.implode('|', Registry::DOMAIN_OPTIONS).')=(.+)$/i';

        foreach ($options as $opt) {
            $opt = trim($opt);
            if (preg_match($reDomainOpt, $opt, $dm)) {
                $optName = strtolower($dm[1]);
                if ($optName === 'from') {
                    $optName = 'domain';
                }

                $sep = str_contains($dm[2], '|') ? '|' : ',';
                $dList = explode($sep, $dm[2]);
                foreach ($dList as $d) {
                    $domains[] = [
                        'name' => strtolower(trim($d)),
                        'type' => $optName,
                    ];
                }
            }
        }

        return $domains;
    }

    /**
     * Extracts domains from a pattern if it starts with || (e.g. ||example.com/ads/).
     *
     * @return list<array{name: string, type: string}>
     */
    private function extractDomainsFromPattern(string $pattern): array
    {
        if (str_starts_with($pattern, '||')) {
            $end = strpos($pattern, '^');
            if ($end === false) {
                $end = strpos($pattern, '/');
            }
            if ($end === false) {
                $end = strlen($pattern);
            }

            $domain = substr($pattern, 2, $end - 2);
            if ($domain !== '') {
                return [['name' => $domain, 'type' => 'domain']];
            }
        }

        return [];
    }

    /**
     * @param list<string> $options
     * @param string|list<string> $target
     */
    private function hasOption(array $options, string|array $target): bool
    {
        $targets = is_array($target) ? array_map('strtolower', $target) : [strtolower($target)];

        foreach ($options as $opt) {
            if (in_array(strtolower(trim($opt)), $targets, true)) {
                return true;
            }
        }

        return false;
    }

    private function buildRegex(string $pattern): string
    {
        if (isset($this->regexCache[$pattern])) {
            return $this->regexCache[$pattern];
        }

        // If it is already a native regex pattern (e.g., /.../), return it as is
        if (str_starts_with($pattern, '/') && str_ends_with($pattern, '/')) {
            $innerRegex = substr($pattern, 1, -1);

            return $this->regexCache[$pattern] = '`'.$innerRegex.'`i';
        }

        // Escape regular expression special characters to treat the pattern as a literal string
        $regex = preg_quote($pattern, '`');

        // Convert adblock wildcard syntax (*) into regular expression matching (.*)
        $regex = str_replace('\*', '.*', $regex);
        $regex = str_replace('\^', '([^a-zA-Z0-9_%\.\-]|$)', $regex);

        // Trait: Enforce a strict trailing boundary for alphanumeric patterns.
        // Prevents partial matches at the end of a domain (e.g., ensuring 'alitems.co'
        // does not falsely cover 'alitems.com').
        if (preg_match('/[a-zA-Z0-9]$/', $pattern)) {
            $regex .= '([^a-z0-9\.\-]|$)';
        }

        // Trait: Enforce a strict leading boundary for alphanumeric plain domains.
        // Uses a positive lookbehind to ensure the pattern is preceded either by the start
        // of the string, a valid subdomain dot separator, or a non-host connector character.
        // This allows 'youtube.com' to cover 'www.youtube.com' (via dot), while stopping
        // 'adnow.com' from partially matching inside 'ads1-adnow.com' (via hyphen).
        if (preg_match('/^[a-zA-Z0-9]/', $pattern)) {
            $finalRegex = '`(?<=^|[^a-z0-9\-])'.$regex.'`i';
        } else {
            $finalRegex = '`'.$regex.'`i';
        }

        return $this->regexCache[$pattern] = $finalRegex;
    }

    private function getPrimaryToken(string $pattern): ?string
    {
        // Prioritize domain token for ||...^ rules
        if ($this->isAnchoredDomain($pattern)) {
            return $this->getAnchoredDomainToken($pattern);
        }

        // regex
        if (str_starts_with($pattern, '/') && str_ends_with($pattern, '/')) {
            return null;
        }

        if (preg_match_all('/[a-z0-9]{3,}/i', $pattern, $matches)) {
            $longest = '';
            foreach ($matches[0] as $match) {
                if (strlen($match) > strlen($longest)) {
                    $longest = $match;
                }
            }

            return $longest !== '' ? strtolower($longest) : null;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function getAllTokens(string $pattern): array
    {
        $tokens = [];

        // For ||...^ rules, also include domain token (but primary token already covers)
        if ($this->isAnchoredDomain($pattern)) {
            $domainToken = $this->getAnchoredDomainToken($pattern);
            if ($domainToken !== null) {
                $tokens[$domainToken] = true;
            }
        }

        if (preg_match_all('/[a-z0-9]{3,}/i', $pattern, $matches)) {
            foreach ($matches[0] as $match) {
                $tokens[strtolower($match)] = true;
            }
        }

        return array_keys($tokens);
    }

    /**
     * Get domain token for patterns like ||example.com^ or ||example.com/path
     */
    private function getAnchoredDomainToken(string $pattern): ?string
    {
        // Find end of domain: either '^' or '/' or end of string
        $end = strpos($pattern, '^');
        if ($end === false) {
            $end = strpos($pattern, '/');
        }
        if ($end === false) {
            $end = strlen($pattern);
        }

        // Extract domain part (between '||' and the separator)
        $domain = substr($pattern, 2, $end - 2);

        if (str_contains($domain, '*')) {
            return null;
        }

        return strtolower($domain);
    }

    /**
     * Check if pattern is a domain-anchored network rule (||...)
     */
    private function isAnchoredDomain(string $pattern): bool
    {
        return str_starts_with($pattern, '||');
    }
}
