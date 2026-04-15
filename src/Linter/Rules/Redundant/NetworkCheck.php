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
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        if (!$this->config->rules['no_dupe_rules']) {
            return [];
        }

        $errors = [];
        $exactSeen = [];
        $genericNet = [];   // Mixed Casing Pattern -> LineNum
        $patternOptionsSeen = []; // Mixed Casing Pattern -> Normalized Options -> [Lowercase Domain -> LineNum]

        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if (Util::isCommentOrEmpty($line) || str_starts_with($line, '[$')) {
                continue;
            }

            if (preg_match(Regex::IS_COSMETIC_RULE, $line)) {
                continue;
            }

            // Determine if the rule is case-sensitive
            $isNetwork = preg_match(Regex::NET_OPTION, $line, $m);
            $optionsStr = $isNetwork ? $m[2] : '';
            $options = $isNetwork ? Util::splitOptions($optionsStr) : [];
            $hasMatchCase = false;
            foreach ($options as $opt) {
                if (strtolower(trim($opt)) === 'match-case') {
                    $hasMatchCase = true;
                    break;
                }
            }

            // 1. Exact duplicate check
            $exactKey = $hasMatchCase ? $line : strtolower($line);
            if (isset($exactSeen[$exactKey])) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Redundant filter: %s already defined on line %d.',
                    $line, $exactSeen[$exactKey],
                ))->line($lineNum)->build();

                continue;
            }
            $exactSeen[$exactKey] = $lineNum;

            // 2. Redundancy check
            $pattern = $isNetwork ? $m[1] : $line;
            if (!$hasMatchCase) {
                $pattern = strtolower($pattern);
            }

            if (!$isNetwork) {
                // No options. Global for this pattern.
                $genericNet[$pattern] = $lineNum;
            } else {
                if (isset($genericNet[$pattern])) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Redundant filter: %s already covered by %s on line %d.',
                        $line, $m[1], $genericNet[$pattern],
                    ))->line($lineNum)->build();
                } else {
                    // Check for domain-level redundancy
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

                    // Redundancy check is skipped for mixed contexts (e.g. domain + to)
                    $isMixedContext = (isset($domainTypes['domain'])) && (isset($domainTypes['denyallow']) || isset($domainTypes['to']));

                    sort($nonDomainOptions);
                    $optionsKey = implode(',', $nonDomainOptions);

                    if (empty($domains)) {
                        $patternOptionsSeen[$pattern][$optionsKey]['_GLOBAL_'] = $lineNum;
                    } else {
                        if (isset($patternOptionsSeen[$pattern][$optionsKey]['_GLOBAL_'])) {
                            $errors[] = RuleErrorBuilder::message(sprintf("Redundant filter: '%s' is redundant.", $line))
                                ->line($lineNum)
                                ->build();
                        } else {
                            $redundantDomains = [];
                            $domainsToMark = [];

                            foreach ($domains as $d) {
                                if (isset($patternOptionsSeen[$pattern][$optionsKey][$d['type']][$d['name']])) {
                                    $redundantDomains[] = [
                                        'domain' => $d['name'],
                                        'line' => $patternOptionsSeen[$pattern][$optionsKey][$d['type']][$d['name']],
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
                                $patternOptionsSeen[$pattern][$optionsKey][$dm['type']][$dm['name']] = $lineNum;
                            }
                        }
                    }
                }
            }
        }

        return $errors;
    }
}
