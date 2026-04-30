<?php

namespace Realodix\Haiku\Linter\Rules;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Util;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 */
final class DomainCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        $bag = new RuleErrorBuilder;

        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if (Util::isCommentOrEmpty($line) || str_starts_with($line, '[$')) {
                continue;
            }

            // Cosmetic rule
            if (preg_match(Regex::COSMETIC_DOMAIN, $line, $m)) {
                if (trim($m[1]) === '') {
                    continue;
                }

                $this->validateDomains($bag, $lineNum, $m[1], ',');
            }

            // Network rule
            if (preg_match(Regex::NET_OPTION, $line, $m)) {
                $options = Util::splitOptions($m[2]);

                foreach ($options as $option) {
                    $option = trim($option);

                    $parts = explode('=', $option, 2);
                    $name = strtolower(trim($parts[0]));
                    $value = $parts[1] ?? null;

                    if ($value === null) {
                        continue;
                    }

                    if (in_array($name, Registry::DOMAIN_OPTIONS, true)) {
                        $this->validateDomains($bag, $lineNum, $value, '|');
                    }
                }
            }
        }

        return $bag->toArray();
    }

    private function validateDomains(RuleErrorBuilder $bag, int $lineNum, string $domainStr, string $separator): void
    {
        if ($this->containsRegexDomain($domainStr)) {
            return;
        }

        $domains = explode($separator, $domainStr);

        if (count($domains) > 1 && count(array_filter($domains, fn($d) => trim($d) !== '')) === 0) {
            $bag->message('Invalid filter')
                ->line($lineNum)
                ->build();

            return;
        }

        $state = [
            'seen' => [],
            'duplicates' => [],
            'inclusions' => [],
            'exclusions' => [],
            'contradictions' => [],
        ];

        foreach ($domains as $index => $domain) {
            if ($this->checkEmptyDomain($bag, $lineNum, $domains, $index)) {
                continue;
            }

            $this->checkBadDomainName($bag, $lineNum, $domain);
            $this->checkAncestorContexts($bag, $lineNum, $domain, $separator);
            $this->checkLowercase($bag, $lineNum, $domain);

            $this->trackDuplicate($domain, $state);
            $this->trackContradiction($domain, $state);
        }

        $this->reportStatefulErrors($bag, $lineNum, $state);
    }

    /**
     * Check if the given domain is empty.
     *
     * @param list<string> $domains
     */
    private function checkEmptyDomain(RuleErrorBuilder $bag, int $lineNum, array $domains, int $index): bool
    {
        $domain = trim($domains[$index]);

        if ($domain !== '') {
            return false;
        }

        $prev = isset($domains[$index - 1]) ? trim($domains[$index - 1]) : null;
        $next = isset($domains[$index + 1]) ? trim($domains[$index + 1]) : null;

        $context = '';

        if ($prev !== null && $prev !== '' && $next !== null && $next !== '') {
            $context = sprintf(' between "%s" and "%s"', $prev, $next);
        } elseif ($prev !== null && $prev !== '') {
            $context = sprintf(' after "%s"', $prev);
        } elseif ($next !== null && $next !== '') {
            $context = sprintf(' before "%s"', $next);
        }

        $bag->message(sprintf('Unexpected empty domain%s.', $context))
            ->line($lineNum)
            ->build();

        return true;
    }

    /**
     * Check if the domain name is bad.
     *
     * rNames:
     * - no-invalid-domains
     */
    private function checkBadDomainName(RuleErrorBuilder $bag, int $lineNum, string $domain): void
    {
        if (strlen($domain) < 2 && $domain !== '*') {
            $bag->message(sprintf('Bad domain name: "%s"', $domain))
                ->line($lineNum)
                ->build();
        }

        if (str_ends_with($domain, '.') && !preg_match('/^[\d\.]+$/', $domain)
            || str_starts_with($domain, '.')
            || str_contains($domain, '/')
        ) {
            $bag->message(sprintf('Bad domain name: "%s"', $domain))
                ->tip(sprintf('Did you mean "%s"?', $domain.'*'))
                ->line($lineNum)
                ->build();
        }

        if (preg_match('/\s/', $domain)) {
            $bag->message(sprintf(
                'Bad domain name: "%s" contains unnecessary whitespace.',
                $domain,
            ))->line($lineNum)->build();
        }
    }

    private function checkAncestorContexts(RuleErrorBuilder $bag, int $lineNum, string $domain, string $separator): void
    {
        if (!str_ends_with($domain, '>')) {
            return;
        }

        if ($separator === '|') {
            $bag->message(sprintf(
                'Bad domain name: "%s". The network filter does not support ancestor context.',
                $domain,
            ))->line($lineNum)->build();

            return;
        }

        preg_match('/([^>]+)([>]+)/', $domain, $m);

        if (strlen($m[2]) !== 2) {
            $bag->message(sprintf('Bad domain name: "%s"', $domain))
                ->tip(sprintf('Did you mean "%s"?', $m[1].'>>'))
                ->line($lineNum)
                ->build();
        }
    }

    /**
     * Check if the domain is lowercase.
     */
    private function checkLowercase(RuleErrorBuilder $bag, int $lineNum, string $domain): void
    {
        if (!$this->config->rules['domain_case']) {
            return;
        }

        if (strtolower($domain) !== $domain) {
            $bag->message(sprintf('Domain %s must be lowercase.', $domain))
                ->line($lineNum)
                ->build();
        }
    }

    /**
     * Tracks duplicate domains.
     *
     * If the domain has been seen before, it is added to the list of duplicates.
     * Otherwise, it is marked as seen.
     *
     * @param string $domain The domain to track.
     * @param array<string, mixed> $state The state array to modify.
     */
    private function trackDuplicate(string $domain, array &$state): void
    {
        if (!$this->config->rules['no_dupe_domains']) {
            return;
        }

        if (isset($state['seen'][$domain])) {
            $state['duplicates'][] = $domain;
        }

        $state['seen'][$domain] = true;
    }

    /**
     * Tracks contradictory domains.
     *
     * If the domain is negated (~domain), it is checked against the list of inclusions.
     * If the domain is not negated, it is checked against the list of exclusions.
     * If a contradictory domain is found, it is added to the list of contradictions.
     * Otherwise, the domain is marked as either included or excluded.
     *
     * @param string $domain The domain to track.
     * @param array<string, mixed> $state The state array to modify.
     */
    private function trackContradiction(string $domain, array &$state): void
    {
        $isNegated = str_starts_with($domain, '~');
        $domain = ltrim($domain, '~');

        if ($isNegated) {
            if (isset($state['inclusions'][$domain])) {
                $state['contradictions'][] = $domain;
            }
            $state['exclusions'][$domain] = true;
        } else {
            if (isset($state['exclusions'][$domain])) {
                $state['contradictions'][] = $domain;
            }
            $state['inclusions'][$domain] = true;
        }
    }

    /**
     * Reports any duplicate or contradictory domains found during analysis.
     *
     * This function will iterate through the state array and report any duplicate
     * or contradictory domains found.
     *
     * @param int $lineNum The line number where the errors were found.
     * @param array<string, mixed> $state The state array to modify.
     */
    private function reportStatefulErrors(RuleErrorBuilder $bag, int $lineNum, array $state): void
    {
        foreach (array_unique($state['duplicates']) as $dup) {
            $bag->message(sprintf('Duplicate domain "%s".', $dup))
                ->line($lineNum)
                ->build();
        }

        foreach (array_unique($state['contradictions']) as $cntr) {
            $bag->message(sprintf('Contradictory domain %s detected.', $cntr))
                ->line($lineNum)
                ->build();
        }
    }

    private function containsRegexDomain(string $domainStr): bool
    {
        $domainStr = trim($domainStr);

        return (str_starts_with($domainStr, '/') && str_ends_with($domainStr, '/'))
            || (str_contains($domainStr, '/') && preg_match('/[\\^([{$\\\]/', $domainStr));
    }
}
