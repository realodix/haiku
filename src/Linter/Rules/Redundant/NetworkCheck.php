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
 * @phpstan-type _RulesData array{
 *  line: string,
 *  type: string,
 *  pattern: string,
 *  options: list<string>,
 *  nonDomainOptions: list<string>,
 *  optionsKey: string,
 *  domains: list<array{name: string, type: string}>,
 *  hasOptions: bool,
 *  hasDomains: bool,
 *  hasMatchCase: bool,
 *  regex: string,
 *  tokens: list<string>,
 * }
 * @phpstan-type _GlobalRuleData array{
 *  pattern: string,
 *  lineNum: int,
 *  hasOptions: bool,
 *  hasMixedDomains: bool,
 *  isAlmostGlobal: bool,
 *  domains: list<array{name: string, type: string}>,
 *  optionsKey: string,
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
     *   'exact': array<string, int>,
     *   'pattern_options': array<string, array<string, array<string, array<string, int>>>>,
     * }
     */
    private array $seen;

    /**
     * Global redundancy checking
     *
     * @var array{
     *   'by_token': array<string, array<string, list<_GlobalRuleData>>>,
     *   'no_token': array<string, list<_GlobalRuleData>>,
     *   'stored': array<string, array<string, bool>>,
     * }
     */
    private array $globalIndex;

    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        if (!$this->config->rules['no_dupe_rules']) {
            return [];
        }

        $this->reset();
        $err = new RuleErrorBuilder;
        /** @var list<_RulesData> */
        $rulesData = [];

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
            $regexStr = $this->buildRegex($pattern);
            $optionsKey = implode(',', $nonDomainOpts);

            $rulesData[$lineNum] = [
                'line' => $line,
                'type' => $type,
                'pattern' => $pattern,
                'options' => $opts,
                'nonDomainOptions' => $nonDomainOpts,
                'optionsKey' => $optionsKey,
                'domains' => $domains,
                'hasOptions' => $hasOpts,
                'hasDomains' => !empty($domains),
                'hasMatchCase' => $hasMatchCase,
                'regex' => $regexStr,
                'tokens' => $this->getAllTokens($pattern),
            ];

            if ($hasOpts) {
                $seenMap = &$this->seen['pattern_options'][$type][$pattern][$optionsKey];
                // if (empty($domains)) {
                //     $seenMap['*'][$lineNum] = true;
                // } else {
                //     foreach ($domains as $d) {
                //         $seenMap[$d['type'].':'.$d['name']][$lineNum] = true;
                //     }
                // }
                // related to checkDomainRedundancy() (scenario B)
                foreach ($domains as $d) {
                    $entityKey = $d['type'].':'.$d['name'];

                    if (!isset($seenMap[$entityKey])) {
                        $seenMap[$entityKey] = $lineNum;
                    }
                }
            }

            // A rule is considered "specific" only if it contains only an inclusion list and has no negated domain.
            $isSpecific = ($domains !== [] && !str_starts_with($domains[0]['name'], '~')) && !$this->isMixedDomains($domains);
            if (!$isSpecific) {
                $uniqueKey = $pattern.'::'.$optionsKey.'::'.implode(',', array_column($domains, 'name'));
                if (!isset($this->globalIndex['stored'][$type][$uniqueKey])) {
                    $this->globalIndex['stored'][$type][$uniqueKey] = true;

                    $token = $this->getPrimaryToken($pattern);
                    $isMixed = $this->isMixedDomains($domains);
                    $isAlmostGlobal = false;
                    if (!$isMixed && $domains !== []) {
                        $isAlmostGlobal = str_starts_with($domains[0]['name'], '~');
                    }

                    $ruleData = [
                        'pattern' => $pattern,
                        'lineNum' => $lineNum,
                        'hasOptions' => $hasOpts,
                        'hasMixedDomains' => $isMixed,
                        'isAlmostGlobal' => $isAlmostGlobal,
                        'domains' => $domains,
                        'optionsKey' => $optionsKey,
                        'regex' => $regexStr,
                    ];

                    if ($token !== null) {
                        $this->globalIndex['by_token'][$type][$token][] = $ruleData;
                    } else {
                        $this->globalIndex['no_token'][$type][] = $ruleData;
                    }
                }
            }
        }

        // Pass 2: Check redundancy
        foreach ($rulesData as $lineNum => $data) {
            // 1. Exact duplicate check (checks if the exact same line was already seen)
            if ($this->checkExactDuplicate($err, $lineNum, $data)) {
                continue;
            }

            // 2. Redundancy check against global rules (no domains)
            // This covers both purely generic rules and rules with matching options.
            if ($this->checkGlobalRedundancy($err, $lineNum, $data)) {
                continue;
            }

            // 3. Domain level redundancy (only for rules that specify domains)
            if ($data['hasDomains']) {
                $this->checkDomainRedundancy($err, $lineNum, $data);
            }
        }

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
    }

    private function shouldSkip(string $line): bool
    {
        if (Util::isCommentOrEmpty($line) || str_starts_with($line, '[$')) {
            return true;
        }

        return (bool) preg_match(Regex::IS_COSMETIC_RULE, $line);
    }

    /**
     * @param _RulesData $data
     */
    private function checkExactDuplicate(RuleErrorBuilder $err, int $lineNum, $data): bool
    {
        $line = $data['line'];
        $exactKey = $data['hasMatchCase'] ? $line : strtolower($line);
        if (isset($this->seen['exact'][$exactKey])) {
            $err->message(sprintf(
                'Redundant filter: %s already defined on line %d.',
                $line, $this->seen['exact'][$exactKey],
            ))->line($lineNum)->build();

            return true;
        }

        $this->seen['exact'][$exactKey] = $lineNum;

        return false;
    }

    /**
     * @param _RulesData $data
     */
    private function checkGlobalRedundancy(RuleErrorBuilder $err, int $lineNum, array $data): bool
    {
        $pattern = $data['pattern'];
        $opts = $data['options'];
        $type = $data['type'];

        /** @var _GlobalRuleData|null */
        $best = null;

        $bucketsToCheck = [];
        foreach ($data['tokens'] as $token) {
            if (isset($this->globalIndex['by_token'][$type][$token])) {
                $bucketsToCheck[] = $this->globalIndex['by_token'][$type][$token];
            }
        }
        if (!empty($this->globalIndex['no_token'][$type])) {
            $bucketsToCheck[] = $this->globalIndex['no_token'][$type];
        }

        foreach ($bucketsToCheck as $bucket) {
            foreach ($bucket as $candidate) {
                if ($lineNum === $candidate['lineNum']) {
                    continue;
                }

                if ($candidate['optionsKey'] !== '') {
                    if ($data['optionsKey'] === '' || $candidate['optionsKey'] !== $data['optionsKey']) {
                        continue;
                    }
                }

                if (!$this->isCovered($data, $candidate)) {
                    continue;
                }

                if (!$data['hasOptions'] && !$data['hasDomains'] && $lineNum < $candidate['lineNum']) {
                    if (preg_match($data['regex'], $candidate['pattern'])) {
                        continue;
                    }
                }

                if ($best === null || $this->isBetter($candidate, $best)) {
                    $best = $candidate;
                }
            }
        }

        if ($best !== null) {
            // The 'popup' option is a special case that avoids redundancy
            if ($data['hasOptions'] && $this->hasOption($opts, 'popup')) {
                return false;
            }

            // If patterns and options are identical, it's a direct duplicate
            if (!$data['hasDomains']
                && $best['hasOptions'] === $data['hasOptions']
                && $pattern === $best['pattern']
            ) {
                // if ($lineNum < $best['lineNum']) {
                //     return false;
                // }

                $err->message(sprintf(
                    'Redundant filter: %s already defined on line %d.',
                    $data['line'], $best['lineNum'],
                ))->line($lineNum)->build();

                return true;
            }

            // Adjust message based on candidate type for domain-specific filters
            if ($data['hasDomains'] && $best['hasOptions']) {
                $err->message(sprintf(
                    'Redundant filter: %s already covered by global filter on line %d.',
                    Str::limit($data['line'], 80), $best['lineNum'],
                ))->line($lineNum)->build();

                return true;
            }

            $err->message(sprintf(
                'Redundant filter: %s already covered by %s on line %d.',
                Str::limit($data['line'], 80), $best['pattern'], $best['lineNum'],
            ))->line($lineNum)->build();

            return true;
        }

        return false;
    }

    /**
     * @param _RulesData $data
     */
    private function checkDomainRedundancy(RuleErrorBuilder $err, int $lineNum, array $data): void
    {
        // $line = $data['line'];
        $type = $data['type'];
        $optionsKey = $data['optionsKey'];

        $seenMap = &$this->seen['pattern_options'][$type][$data['pattern']][$optionsKey];

        // Scenario B: The current rule is covered by a GLOBAL rule (with same options).
        // if (isset($seenMap['*'])) {
        //     $atLine = (int) array_key_first($seenMap['*']);
        //     $errors[] = RuleErrorBuilder::message(sprintf(
        //         'Redundant filter: %s already covered by global filter on line %d.',
        //         $line, $atLine,
        //     ))->line($lineNum)->build();

        //     return $errors;
        // }

        // The rule is DOMAIN-SPECIFIC and not covered by a GLOBAL rule.
        // Check if individual domains are redundant against previous domain-specific rules.
        $domainTypes = [];
        foreach ($data['domains'] as $d) {
            $domainTypes[$d['type']] = true;
        }
        $isMixedContext = isset($domainTypes['domain'])
            && (isset($domainTypes['denyallow']) || isset($domainTypes['to']));

        $redundantDomains = [];

        foreach ($data['domains'] as $d) {
            $entityKey = $d['type'].':'.$d['name'];
            if (isset($seenMap[$entityKey]) && $lineNum > $seenMap[$entityKey]) {
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
                ))->line($lineNum)->build();
            }
        }
    }

    /**
     * Determine if a network rule is semantically and domain-wise covered by a candidate rule.
     *
     * A rule is covered if:
     * 1. The candidate's pattern matches the target rule's pattern.
     * 2. The candidate's domain list encompasses all domains where the target rule applies.
     * 3. Rules with mixed domains (standard and negated) only cover rules with the exact same domain set.
     *
     * @param _RulesData $rule The rule being checked for redundancy.
     * @param _GlobalRuleData $candidate The candidate rule that might cover the target rule.
     */
    private function isCovered(array $rule, array $candidate): bool
    {
        if (!@preg_match($candidate['regex'], $rule['pattern'])) {
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
     * @param _GlobalRuleData $candidate The rule to evaluate.
     * @param _GlobalRuleData $best The current best rule to compare against.
     */
    private function isBetter(array $candidate, array $best): bool
    {
        // 1. Semantic generality in Pattern
        $candCoversBest = preg_match($candidate['regex'], $best['pattern']);
        $bestCoversCand = preg_match($best['regex'], $candidate['pattern']);

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
     * @param _GlobalRuleData $rule The rule to check against.
     */
    private function isDomainMatched(string $domain, array $rule): bool
    {
        // just defensive programming
        // if ($rule['domains'] === []) {
        //     return true;
        // }

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
     */
    private function hasOption(array $options, string $target): bool
    {
        foreach ($options as $opt) {
            if (strtolower(trim($opt)) === $target) {
                return true;
            }
        }

        return false;
    }

    private function buildRegex(string $pattern): string
    {
        // If it is already a native regex pattern (e.g., /.../), return it as is
        if (str_starts_with($pattern, '/') && str_ends_with($pattern, '/')) {
            $innerRegex = substr($pattern, 1, -1);

            return '`'.$innerRegex.'`i';
        }

        // Escape regular expression special characters to treat the pattern as a literal string
        $regex = preg_quote($pattern, '`');

        // Convert adblock wildcard syntax (*) into regular expression matching (.*)
        $regex = str_replace('\*', '.*', $regex);

        // Trait: Enforce a strict trailing boundary for alphanumeric patterns.
        // Prevents partial matches at the end of a domain (e.g., ensuring 'alitems.co'
        // does not falsely cover 'alitems.com').
        if (preg_match('/[a-zA-Z0-9]$/', $pattern)) {
            $regex .= '([^a-z0-9\.\-]|$)';
        }

        // Trait: Enforce a strict leading boundary for alphanumeric plain domains.
        // Uses a positive lookbehind to ensure the pattern is preceded either by the start
        // of the string or a non-host connector character. This stops 'adnow.com' from
        // partially matching inside 'ads1-adnow.com'.
        if (preg_match('/^[a-zA-Z0-9]/', $pattern)) {
            return '`(?<=^|[^a-z0-9\-.])'.$regex.'`i';
        }

        return '`'.$regex.'`i';
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
     * Get domain token for patterns like ||example.com^ or ||example.com/path^
     */
    private function getAnchoredDomainToken(string $pattern): ?string
    {
        // Find end of domain: either '^' or '/' or end of string
        $end = strpos($pattern, '^');
        // Extract domain part (between '||' and the separator)
        $domain = substr($pattern, 2, $end - 2);

        if (str_contains($domain, '*')) {
            return null;
        }

        return strtolower($domain);
    }

    /**
     * Check if pattern is a domain-anchored network rule (||...^)
     */
    private function isAnchoredDomain(string $pattern): bool
    {
        return str_starts_with($pattern, '||') && str_contains($pattern, '^');
    }
}
