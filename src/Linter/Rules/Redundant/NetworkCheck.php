<?php

namespace Realodix\Haiku\Linter\Rules\Redundant;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Linter\Util;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 */
final class NetworkCheck implements Rule
{
    /** @var array<string, int> */
    private array $exactSeen = [];

    /** @var array<string, int> */
    private array $genericNet = [];

    /** @var array<string, array<string, array<string, array<string, int>>>> */
    private array $patternOptionsSeen = [];

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

        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if ($this->shouldSkip($line)) {
                continue;
            }

            // Determine if the rule is case-sensitive
            $isNetwork = (bool) preg_match(Regex::NET_OPTION, $line, $m);
            $optionsStr = $isNetwork ? $m[2] : '';
            $options = $isNetwork ? Util::splitOptions($optionsStr) : [];
            $hasMatchCase = $this->hasOption($options, 'match-case');

            // 1. Exact duplicate check
            if ($err = $this->checkExactDuplicate($lineNum, $line, $hasMatchCase)) {
                $errors[] = $err;

                continue;
            }

            // 2. Redundancy check
            $pattern = $isNetwork ? $m[1] : $line;
            if (!$hasMatchCase) {
                $pattern = strtolower($pattern);
            }

            if (!$isNetwork) {
                // No options. Global for this pattern.
                $this->genericNet[$pattern] = $lineNum;

                continue;
            }

            if ($err = $this->checkGenericRedundancy($lineNum, $line, $pattern, $m[1], $options)) {
                $errors[] = $err;

                continue;
            }

            $errors = [...$errors, ...$this->checkDomainRedundancy($lineNum, $line, $pattern, $options, $hasMatchCase)];
        }

        return $errors;
    }

    /**
     * @return _RuleError|null
     */
    private function checkExactDuplicate(int $lineNum, string $line, bool $hasMatchCase): ?array
    {
        $exactKey = $hasMatchCase ? $line : strtolower($line);

        if (isset($this->exactSeen[$exactKey])) {
            return RuleErrorBuilder::message(sprintf(
                'Redundant filter: %s already defined on line %d.',
                $line, $this->exactSeen[$exactKey],
            ))->line($lineNum)->build();
        }

        $this->exactSeen[$exactKey] = $lineNum;

        return null;
    }

    /**
     * @param list<string> $options
     * @return _RuleError|null
     */
    private function checkGenericRedundancy(
        int $lineNum,
        string $line,
        string $pattern,
        string $rawPattern,
        array $options,
    ): ?array {
        if (!isset($this->genericNet[$pattern])) {
            return null;
        }

        if ($this->hasOption($options, 'popup')) {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(
            'Redundant filter: %s already covered by %s on line %d.',
            $line, $rawPattern, $this->genericNet[$pattern],
        ))->line($lineNum)->build();
    }

    /**
     * @param list<string> $options
     * @return _RuleError[]
     */
    private function checkDomainRedundancy(
        int $lineNum,
        string $line,
        string $pattern,
        array $options,
        bool $hasMatchCase,
    ): array {
        $errors = [];
        [$nonDomainOptions, $domains, $domainTypes] = $this->parseDomains($options, $hasMatchCase);

        // Redundancy check is skipped for mixed contexts (e.g. domain + to)
        $isMixedContext = isset($domainTypes['domain'])
            && (isset($domainTypes['denyallow']) || isset($domainTypes['to']));

        sort($nonDomainOptions);
        $optionsKey = implode(',', $nonDomainOptions);

        if (empty($domains)) {
            if (isset($this->patternOptionsSeen[$pattern][$optionsKey]['_GLOBAL_']['_GLOBAL_'])) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Redundant filter: %s already defined on line %d.',
                    $line, $this->patternOptionsSeen[$pattern][$optionsKey]['_GLOBAL_']['_GLOBAL_'],
                ))->line($lineNum)->build();
            } else {
                $this->patternOptionsSeen[$pattern][$optionsKey]['_GLOBAL_']['_GLOBAL_'] = $lineNum;
            }

            return $errors;
        }

        if (isset($this->patternOptionsSeen[$pattern][$optionsKey]['_GLOBAL_']['_GLOBAL_'])) {
            $errors[] = RuleErrorBuilder::message(sprintf("Redundant filter: '%s' is redundant.", $line))
                ->line($lineNum)
                ->build();

            return $errors;
        }

        $redundantDomains = [];
        $domainsToMark = [];

        foreach ($domains as $d) {
            if (isset($this->patternOptionsSeen[$pattern][$optionsKey][$d['type']][$d['name']])) {
                $redundantDomains[] = [
                    'domain' => $d['name'],
                    'line' => $this->patternOptionsSeen[$pattern][$optionsKey][$d['type']][$d['name']],
                ];
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

        // Mark domains as seen for subsequent rules
        foreach ($domainsToMark as $dm) {
            $this->patternOptionsSeen[$pattern][$optionsKey][$dm['type']][$dm['name']] = $lineNum;
        }

        return $errors;
    }

    private function reset(): void
    {
        $this->exactSeen = [];
        $this->genericNet = [];
        $this->patternOptionsSeen = [];
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
     * @param list<string> $options
     * @return array{
     *  0: list<string>,
     *  1: array<int, array{name: string, type: string}>,
     *  2: array<string, bool>,
     * }
     */
    private function parseDomains(array $options, bool $hasMatchCase): array
    {
        $nonDomainOptions = [];
        $domains = [];
        $domainTypes = [];

        foreach ($options as $opt) {
            $opt = trim($opt);
            if (preg_match('/^(domain|from|to|denyallow)=(.+)$/i', $opt, $dm)) {
                $optName = strtolower($dm[1]);
                if ($optName === 'from') {
                    $optName = 'domain';
                }
                $domainTypes[$optName] = true;

                $sep = str_contains($dm[2], '|') ? '|' : ',';
                $dList = explode($sep, $dm[2]);
                foreach ($dList as $d) {
                    $domains[] = [
                        'name' => strtolower(trim($d)),
                        'type' => $optName,
                    ];
                }
            } else {
                $nonDomainOptions[] = $hasMatchCase ? $opt : strtolower($opt);
            }
        }

        return [$nonDomainOptions, $domains, $domainTypes];
    }
}
