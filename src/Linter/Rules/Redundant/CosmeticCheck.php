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
final class CosmeticCheck implements Rule
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
        $genericCosm = []; // Separator + Selector -> LineNum
        $domainSeen = [];  // Separator + Selector -> [Lowercase Domain -> LineNum]

        foreach ($content as $index => $line) {
            $line = trim($line);
            $lineNum = $index + 1;

            if (Util::isCommentOrEmpty($line) || str_starts_with($line, '[$')) {
                continue;
            }

            if (!preg_match(Regex::IS_COSMETIC_RULE, $line)) {
                continue;
            }

            // 1. Exact duplicate check (case-sensitive)
            if (isset($exactSeen[$line])) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    "Redundant filter: '%s' is already defined on line %d.",
                    $line, $exactSeen[$line],
                ))->line($lineNum)->build();

                continue;
            }
            $exactSeen[$line] = $lineNum;

            // 2. Redundancy check
            if (preg_match(Regex::COSMETIC_RULE, $line, $m)) {
                $domainStr = trim($m[3]);
                $sepSelector = $m[4].$m[5]; // e.g. "##.ads"

                if ($domainStr === '') {
                    // Global rule
                    $genericCosm[$sepSelector] = $lineNum;
                } else {
                    // Rule with domains
                    if (isset($genericCosm[$sepSelector])) {
                        // Already covered by global rule
                        $errors[] = RuleErrorBuilder::message(sprintf(
                            "Redundant filter: '%s' is redundant as it is already covered by '%s' on line %d.",
                            $line, $sepSelector, $genericCosm[$sepSelector],
                        ))->line($lineNum)->build();
                    } else {
                        // Check for overlapping domains
                        $domains = explode(',', $domainStr);
                        $redundantDomains = [];
                        $domainsToMark = [];

                        foreach ($domains as $domain) {
                            $domain = trim($domain);
                            $lowDomain = strtolower($domain);

                            if (isset($domainSeen[$sepSelector][$lowDomain])) {
                                $redundantDomains[] = [
                                    'domain' => $domain,
                                    'line' => $domainSeen[$sepSelector][$lowDomain],
                                ];
                            } else {
                                $domainsToMark[] = $lowDomain;
                            }
                        }

                        if (!empty($redundantDomains)) {
                            foreach ($redundantDomains as $rd) {
                                $errors[] = RuleErrorBuilder::message(sprintf(
                                    "Redundant filter: domain '%s' is redundant as it is already covered on line %d.",
                                    $rd['domain'], $rd['line'],
                                ))->line($lineNum)->build();
                            }
                        }

                        // Mark domains as seen for subsequent rules
                        foreach ($domainsToMark as $ld) {
                            $domainSeen[$sepSelector][$ld] = $lineNum;
                        }
                    }
                }
            }
        }

        return $errors;
    }
}
