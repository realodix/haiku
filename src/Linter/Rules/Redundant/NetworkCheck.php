<?php

namespace Realodix\Haiku\Linter\Rules\Redundant;

use Illuminate\Support\Str;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
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
 */
final class NetworkCheck implements Rule
{
    private const DOMAIN_OPTIONS = ['domain', 'from', 'to', 'denyallow'];

    /** @var array<string, array<string, int>> */
    private array $exactSeen = [];

    /** @var array<string, array<string, array<string, array<string, array<int, bool>>>>> */
    private array $patternOptionsSeen = [];

    /**
     * @var array<string, array<string, list<array{
     *  pattern: string,
     *  line: int,
     *  hasOptions: bool,
     *  optionsKey: string,
     *  regex: string
     * }>>>
     */
    private array $globalRulesBuckets = [];

    /**
     * @var array<string, list<array{
     *  pattern: string,
     *  line: int,
     *  hasOptions: bool,
     *  optionsKey: string,
     *  regex: string
     * }>>
     */
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
        $errors = [];
        /** @var list<_rulesData> $rulesData */
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
                if (empty($domains)) {
                    $seenMap['*'][$lineNum] = true;
                } else {
                    foreach ($domains as $d) {
                        $seenMap[$d['type'].':'.$d['name']][$lineNum] = true;
                    }
                }
            }

            if (empty($domains)) {
                $uniqueKey = $pattern.'::'.$optionsKey;
                if (!isset($this->globalRulesStored[$type][$uniqueKey])) {
                    $this->globalRulesStored[$type][$uniqueKey] = true;

                    $isRegex = str_starts_with($pattern, '/') && str_ends_with($pattern, '/');
                    $token = $this->getPrimaryToken($pattern, $isRegex);
                    $regexStr = $this->buildRegex($pattern);

                    $ruleData = [
                        'pattern' => $pattern,
                        'line' => $lineNum,
                        'hasOptions' => $hasOpts,
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
            if ($err = $this->checkExactDuplicate($lineNum, $data)) {
                $errors[] = $err;

                continue;
            }

            // 2. Redundancy check against global rules (no domains)
            // This covers both purely generic rules and rules with matching options.
            if ($err = $this->checkGlobalRedundancy($lineNum, $data)) {
                $errors[] = $err;

                continue;
            }

            // 3. Domain level redundancy (only for rules that specify domains)
            if ($data['hasDomains']) {
                $errors = [...$errors, ...$this->checkDomainRedundancy($lineNum, $data)];
            }
        }

        return $errors;
    }

    /**
     * @param _rulesData $data
     * @return _RuleError|null
     */
    private function checkExactDuplicate(int $lineNum, $data): ?array
    {
        $line = $data['line'];
        $exactKey = $data['hasMatchCase'] ? $line : strtolower($line);
        $type = $data['isWhitelist'] ? 'whitelist' : 'blacklist';

        if (isset($this->exactSeen[$type][$exactKey])) {
            return RuleErrorBuilder::message(sprintf(
                'Redundant filter: %s already defined on line %d.',
                $line, $this->exactSeen[$type][$exactKey],
            ))->line($lineNum)->build();
        }

        $this->exactSeen[$type][$exactKey] = $lineNum;

        return null;
    }

    /**
     * @param _rulesData $data
     * @return _RuleError|null
     */
    private function checkGlobalRedundancy(int $lineNum, array $data): ?array
    {
        $pattern = $data['pattern'];
        $opts = $data['options'];
        $type = $data['isWhitelist'] ? 'whitelist' : 'blacklist';

        /** @var array{pattern: string, line: int, hasOptions: bool}|null $best */
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
            foreach ($bucket as $genRule) {
                if ($lineNum === $genRule['line']) {
                    continue;
                }

                if ($genRule['hasOptions']) {
                    if (!$data['hasOptions'] || $genRule['optionsKey'] !== $data['optionsKey']) {
                        continue;
                    }
                }

                if (!preg_match($genRule['regex'], $pattern)) {
                    continue;
                }

                if (!$data['hasOptions'] && !$data['hasDomains'] && $lineNum < $genRule['line']) {
                    if (preg_match($this->buildRegex($pattern), $genRule['pattern'])) {
                        continue;
                    }
                }

                $candidate = [
                    'pattern' => $genRule['pattern'],
                    'line' => $genRule['line'],
                    'hasOptions' => $genRule['hasOptions'],
                ];
                if ($best === null || $this->isBetter($candidate, $best)) {
                    $best = $candidate;
                }
            }
        }

        if ($best !== null) {
            // The 'popup' option is a special case that avoids redundancy
            if ($data['hasOptions'] && $this->hasOption($opts, 'popup')) {
                return null;
            }

            // If patterns and options are identical, it's a direct duplicate
            if (!$data['hasDomains'] && $best['hasOptions'] === $data['hasOptions'] && $pattern === $best['pattern']) {
                if ($lineNum < $best['line']) {
                    return null;
                }

                return RuleErrorBuilder::message(sprintf(
                    'Redundant filter: %s already defined on line %d.',
                    $data['line'], $best['line'],
                ))->line($lineNum)->build();
            }

            // Adjust message based on candidate type for domain-specific filters
            if ($data['hasDomains'] && $best['hasOptions']) {
                return RuleErrorBuilder::message(sprintf(
                    'Redundant filter: %s already covered by global filter on line %d.',
                    Str::limit($data['line'], 80), $best['line'],
                ))->line($lineNum)->build();
            }

            return RuleErrorBuilder::message(sprintf(
                'Redundant filter: %s already covered by %s on line %d.',
                Str::limit($data['line'], 80), $best['pattern'], $best['line'],
            ))->line($lineNum)->build();
        }

        return null;
    }

    /**
     * @param _rulesData $data
     * @return list<_RuleError>
     */
    private function checkDomainRedundancy(int $lineNum, array $data): array
    {
        $errors = [];
        $line = $data['line'];
        $type = $data['isWhitelist'] ? 'whitelist' : 'blacklist';
        $optionsKey = $data['optionsKey'];

        $seenMap = &$this->patternOptionsSeen[$type][$data['pattern']][$optionsKey];

        // Scenario B: The current rule is covered by a GLOBAL rule (with same options).
        if (isset($seenMap['*'])) {
            $atLine = (int) array_key_first($seenMap['*']);
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Redundant filter: %s already covered by global filter on line %d.',
                $line, $atLine,
            ))->line($lineNum)->build();

            return $errors;
        }

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
                            'line' => $atLine,
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
                $errors[] = RuleErrorBuilder::message(sprintf(
                    "Redundant filter: domain '%s' already covered on line %d.",
                    $rd['domain'], $rd['line'],
                ))->line($lineNum)->build();
            }
        }

        // State Update: Mark domains that were not redundant as "seen" for subsequent rules.
        foreach ($domainsToMark as $dm) {
            $entityKey = $dm['type'].':'.$dm['name'];
            $seenMap[$entityKey][$lineNum] = true;
        }

        return $errors;
    }

    private function reset(): void
    {
        $this->exactSeen = ['whitelist' => [], 'blacklist' => []];
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
     * Determine if candidate B is "better" (more general or earlier) than parent C.
     *
     * @param array{pattern: string, line: int, hasOptions: bool} $b
     * @param array{pattern: string, line: int, hasOptions: bool} $c
     */
    private function isBetter(array $b, array $c): bool
    {
        // 1. Generality in Options (No options > some options)
        if (!$b['hasOptions'] && $c['hasOptions']) {
            return true;
        }
        if ($b['hasOptions'] && !$c['hasOptions']) {
            return false;
        }

        // 2. Semantic generality in Pattern
        $bCoversC = preg_match($this->buildRegex($b['pattern']), $c['pattern']);
        $cCoversB = preg_match($this->buildRegex($c['pattern']), $b['pattern']);

        if ($bCoversC && !$cCoversB) {
            return true; // B is strictly more general
        }
        if (!$bCoversC && $cCoversB) {
            return false; // C is strictly more general
        }

        // 3. Line order (Earlier rules are preferred as reference points)
        return $b['line'] < $c['line'];
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
        $reDomainOpt = '/^('.implode('|', self::DOMAIN_OPTIONS).')=/i';

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
        $reDomainOpt = '/^('.implode('|', self::DOMAIN_OPTIONS).')=(.+)$/i';

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
