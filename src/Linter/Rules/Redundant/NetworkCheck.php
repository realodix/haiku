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
 * @phpstan-type _rulesData array{
 *  line: string,
 *  pattern: string,
 *  options: list<string>,
 *  nonDomainOptions: list<string>,
 *  optionsKey: string,
 *  domains: list<array{name: string, type: string}>,
 *  hasOptions: bool,
 *  hasDomains: bool,
 *  hasMatchCase: bool,
 *  isWhitelist: bool,
 * }
 * @phpstan-type _GlobalRuleData array{
 *  pattern: string,
 *  lineNum: int,
 *  hasOptions: bool,
 *  hasMixed: bool,
 *  domains: list<array{name: string, type: string}>,
 *  optionsKey: string,
 *  regex: string
 * }
 */
final class NetworkCheck implements Rule
{
    /** @var array<string, int> */
    private array $exactSeen = [];

    /** @var array<string, array<string, array<string, array<string, array<int, bool>>>>> */
    private array $patternOptionsSeen = [];

    /** @var array<string, array<string, list<_GlobalRuleData>>> */
    private array $globalRulesBuckets = [];

    /** @var array<string, list<_GlobalRuleData>> */
    private array $globalRulesNoToken = [];

    /** @var array<string, array<string, bool>> */
    private array $globalRulesStored = [];

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
        /** @var list<_rulesData> */
        $rulesData = [];

        // Pass 1: Parse and collect state
        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if ($this->shouldSkip($line)) {
                continue;
            }

            $hasOpts = (bool) preg_match(Regex::NET_OPTION, $line, $m);
            $isWhitelist = str_starts_with($line, '@@');
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
            $rulesData[$lineNum] = [
                'line' => $line,
                'pattern' => $pattern,
                'options' => $opts,
                'nonDomainOptions' => $nonDomainOpts,
                'optionsKey' => $optionsKey,
                'domains' => $domains,
                'hasOptions' => $hasOpts,
                'hasDomains' => !empty($domains),
                'hasMatchCase' => $hasMatchCase,
                'isWhitelist' => $isWhitelist,
            ];

            $type = $isWhitelist ? 'whitelist' : 'blacklist';

            if ($hasOpts) {
                $seenMap = &$this->patternOptionsSeen[$type][$pattern][$optionsKey];
                // if (empty($domains)) {
                //     $seenMap['*'][$lineNum] = true;
                // } else {
                //     foreach ($domains as $d) {
                //         $seenMap[$d['type'].':'.$d['name']][$lineNum] = true;
                //     }
                // }
                // related to checkDomainRedundancy() (scenario B)
                foreach ($domains as $d) {
                    $seenMap[$d['type'].':'.$d['name']][$lineNum] = true;
                }
            }

            $isSpecific = $this->isMixed($domains) || ($domains !== [] && !str_starts_with($domains[0]['name'], '~'));
            if (!$isSpecific) {
                $uniqueKey = $pattern.'::'.$optionsKey.'::'.implode(',', array_column($domains, 'name'));
                if (!isset($this->globalRulesStored[$type][$uniqueKey])) {
                    $this->globalRulesStored[$type][$uniqueKey] = true;

                    $isRegex = str_starts_with($pattern, '/') && str_ends_with($pattern, '/');
                    $token = $this->getPrimaryToken($pattern, $isRegex);
                    $regexStr = $this->buildRegex($pattern);

                    $ruleData = [
                        'pattern' => $pattern,
                        'lineNum' => $lineNum,
                        'hasOptions' => $hasOpts,
                        'hasMixed' => false,
                        'domains' => $domains,
                        'optionsKey' => $optionsKey,
                        'regex' => $regexStr,
                    ];

                    if ($token !== null) {
                        $this->globalRulesBuckets[$type][$token][] = $ruleData;
                    } else {
                        $this->globalRulesNoToken[$type][] = $ruleData;
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

    /**
     * @param _rulesData $data
     */
    private function checkExactDuplicate(RuleErrorBuilder $err, int $lineNum, $data): bool
    {
        $line = $data['line'];
        $exactKey = $data['hasMatchCase'] ? $line : strtolower($line);
        if (isset($this->exactSeen[$exactKey])) {
            $err->message(sprintf(
                'Redundant filter: %s already defined on line %d.',
                $line, $this->exactSeen[$exactKey],
            ))->line($lineNum)->build();

            return true;
        }

        $this->exactSeen[$exactKey] = $lineNum;

        return false;
    }

    /**
     * @param _rulesData $data
     */
    private function checkGlobalRedundancy(RuleErrorBuilder $err, int $lineNum, array $data): bool
    {
        $pattern = $data['pattern'];
        $opts = $data['options'];
        $type = $data['isWhitelist'] ? 'whitelist' : 'blacklist';

        /** @var _GlobalRuleData|null */
        $best = null;

        $bucketsToCheck = [];
        $tokens = $this->getAllTokens($pattern);
        foreach ($tokens as $token) {
            if (isset($this->globalRulesBuckets[$type][$token])) {
                $bucketsToCheck[] = $this->globalRulesBuckets[$type][$token];
            }
        }
        if (!empty($this->globalRulesNoToken[$type])) {
            $bucketsToCheck[] = $this->globalRulesNoToken[$type];
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
                    if (preg_match($this->buildRegex($pattern), $candidate['pattern'])) {
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

            // A rule with a negated domain is not redundant against a filter that excludes different domains
            if ($data['hasDomains']) {
                foreach ($data['domains'] as $d) {
                    if (str_starts_with($d['name'], '~')) {
                        if (!$this->isDomainMatched($d['name'], $best['domains'])) {
                            return false;
                        }
                    }
                }
            }

            // If patterns and options are identical, it's a direct duplicate
            if (!$data['hasDomains']
                && $best['hasOptions'] === $data['hasOptions']
                && $pattern === $best['pattern']
            ) {
                if ($lineNum < $best['lineNum']) {
                    return false;
                }

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
     * @param _rulesData $data
     */
    private function checkDomainRedundancy(RuleErrorBuilder $err, int $lineNum, array $data): void
    {
        // $line = $data['line'];
        $type = $data['isWhitelist'] ? 'whitelist' : 'blacklist';
        $optionsKey = $data['optionsKey'];

        $seenMap = &$this->patternOptionsSeen[$type][$data['pattern']][$optionsKey];

        // Scenario B: The current rule is covered by a GLOBAL rule (with same options).
        // if (isset($seenMap['*'])) {
        //     $atLine = (int) array_key_first($seenMap['*']);
        //     $errors[] = RuleErrorBuilder::message(sprintf(
        //         'Redundant filter: %s already covered by global filter on line %d.',
        //         $line, $atLine,
        //     ))->line($lineNum)->build();

        //     return $errors;
        // }

        // Scenario C: The rule is DOMAIN-SPECIFIC and not covered by a GLOBAL rule.
        // Check if individual domains are redundant against previous domain-specific rules.
        $domainTypes = [];
        foreach ($data['domains'] as $d) {
            $domainTypes[$d['type']] = true;
        }
        $isMixedContext = isset($domainTypes['domain'])
            && (isset($domainTypes['denyallow']) || isset($domainTypes['to']));

        $redundantDomains = [];
        $domainsToMark = [];

        foreach ($data['domains'] as $d) {
            $entityKey = $d['type'].':'.$d['name'];
            if (isset($seenMap[$entityKey])) {
                foreach (array_keys($seenMap[$entityKey]) as $atLine) {
                    if ($lineNum > $atLine) {
                        $redundantDomains[] = [
                            'domain' => $d['name'],
                            'atLineNum' => $atLine,
                        ];

                        break;
                    }
                }
            } else {
                $domainsToMark[] = $d;
            }
        }

        if (!$isMixedContext && !empty($redundantDomains)) {
            foreach ($redundantDomains as $rd) {
                $err->message(sprintf(
                    "Redundant filter: domain '%s' already covered on line %d.",
                    $rd['domain'], $rd['atLineNum'],
                ))->line($lineNum)->build();
            }
        }

        // State Update: Mark domains that were not redundant as "seen" for subsequent rules.
        foreach ($domainsToMark as $dm) {
            $entityKey = $dm['type'].':'.$dm['name'];
            $seenMap[$entityKey][$lineNum] = true;
        }
    }

    private function reset(): void
    {
        $this->exactSeen = [];
        $this->patternOptionsSeen = ['whitelist' => [], 'blacklist' => []];
        $this->globalRulesBuckets = ['whitelist' => [], 'blacklist' => []];
        $this->globalRulesNoToken = ['whitelist' => [], 'blacklist' => []];
        $this->globalRulesStored = ['whitelist' => [], 'blacklist' => []];
    }

    private function shouldSkip(string $line): bool
    {
        if (Util::isCommentOrEmpty($line) || str_starts_with($line, '[$')) {
            return true;
        }

        return (bool) preg_match(Regex::IS_COSMETIC_RULE, $line);
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

    /**
     * Determine if the candidate rule is "better" (more general or earlier)
     * than the current best.
     *
     * @param _GlobalRuleData $candidate The rule to evaluate.
     * @param _GlobalRuleData $best The current best rule to compare against.
     */
    private function isBetter(array $candidate, array $best): bool
    {
        // 1. Semantic generality in Pattern
        $bCoversC = preg_match($this->buildRegex($candidate['pattern']), $best['pattern']);
        $cCoversB = preg_match($this->buildRegex($best['pattern']), $candidate['pattern']);

        if ($bCoversC && !$cCoversB) {
            return true; // candidate is strictly more general
        }
        if (!$bCoversC && $cCoversB) {
            return false; // best is strictly more general
        }

        // 2. Generality in Options (No options > some options)
        if ($candidate['optionsKey'] === '' && $best['optionsKey'] !== '') {
            return true;
        }
        if ($candidate['optionsKey'] !== '' && $best['optionsKey'] === '') {
            return false;
        }

        // 3. Line order (Earlier rules are preferred as reference points)
        return $candidate['lineNum'] < $best['lineNum'];
    }

    /**
     * Determine if a network rule is semantically and domain-wise covered by a candidate rule.
     *
     * A rule is covered if:
     * 1. The candidate's pattern (regex) matches the target rule's pattern.
     * 2. The candidate's domain list encompasses all domains where the target rule applies.
     * 3. Rules with mixed domains (~ and +) only cover rules with the exact same domain set.
     *
     * @param _rulesData $rule The rule being checked for redundancy.
     * @param _GlobalRuleData $candidate The candidate rule that might cover the target rule.
     */
    private function isCovered(array $rule, array $candidate): bool
    {
        if (!preg_match($candidate['regex'], $rule['pattern'])) {
            return false;
        }

        if ($candidate['domains'] !== []) {
            // A rule with a mix of inclusions and exclusions should not cover other rules
            // unless they have the exact same domain set.
            if ($this->isMixed($candidate['domains'])) {
                if ($candidate['domains'] !== $rule['domains']) {
                    return false;
                }
            }

            // Rule must be covered by candidate's domains
            $ruleDomains = $rule['domains'] !== [] ? $rule['domains'] : [['name' => '', 'type' => 'domain']];
            foreach ($ruleDomains as $d) {
                if (!$this->isDomainMatched($d['name'], $candidate['domains'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if a specific domain (or global context) is covered by a list of domain filters.
     *
     * A domain is matched if:
     * - It is explicitly present in the domain list.
     * - Or, the list contains ONLY exclusions and the domain is not among them.
     *
     * @param string $domain The domain to check (use empty string for global context).
     * @param list<array{name: string, type: string}> $ruleDomains The domain list to check against.
     */
    private function isDomainMatched(string $domain, array $ruleDomains): bool
    {
        if ($ruleDomains === []) {
            return true;
        }

        foreach ($ruleDomains as $rd) {
            if ($rd['name'] === $domain) {
                return true;
            }
        }

        if (str_starts_with($domain, '~')) {
            return false;
        }

        // Check for implicit inclusion in a rule with only exclusions
        foreach ($ruleDomains as $rd) {
            $isSpecific = $this->isMixed($ruleDomains) || !str_starts_with($ruleDomains[0]['name'], '~');
            if ($isSpecific) {
                return false;
            }
            if ($rd['name'] === '~'.$domain) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the domain list contains both inclusions and exclusions.
     *
     * @param list<array{name: string, type: string}> $domains
     */
    private function isMixed(array $domains): bool
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

    private function buildRegex(string $pattern): string
    {
        if (str_starts_with($pattern, '/') && str_ends_with($pattern, '/')) {
            $innerRegex = substr($pattern, 1, -1);

            return '`'.$innerRegex.'`i';
        }

        $regex = preg_quote($pattern, '`');
        $regex = str_replace('\*', '.*', $regex);

        return '`'.$regex.'`i';
    }

    private function getPrimaryToken(string $pattern, bool $isRegex): ?string
    {
        if ($isRegex) {
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
        if (preg_match_all('/[a-z0-9]{3,}/i', $pattern, $matches)) {
            $tokens = [];
            foreach ($matches[0] as $match) {
                $tokens[strtolower($match)] = true;
            }

            return array_keys($tokens);
        }

        return [];
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
}
