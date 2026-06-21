<?php

namespace Realodix\Haiku\Linter\Rules;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Support\Util;

final class DomainCheck implements Rule
{
    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content, $err): array
    {
        foreach ($content as $index => $line) {
            $err->line($index + 1);
            $line = trim($line);

            if (Util::isCommentOrEmpty($line) || str_starts_with($line, '[$')) {
                continue;
            }

            // Cosmetic rule
            if (preg_match(Regex::COSMETIC_DOMAIN, $line, $m)) {
                if (trim($m[1]) === '') {
                    continue;
                }

                $this->validateDomains($err, $m[1], ',');
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
                        $this->validateDomains($err, $value, '|');
                    }
                }
            }
        }

        return $err->toArray();
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function validateDomains($err, string $domainStr, string $separator): void
    {
        if ($this->containsRegexDomain($domainStr)) {
            return;
        }

        $domains = explode($separator, $domainStr);

        if (count($domains) > 1 && count(array_filter($domains, fn($d) => trim($d) !== '')) === 0) {
            $err->message('Invalid filter.')
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
            if ($this->checkEmptyDomain($err, $domains, $index)) {
                continue;
            }

            $this->checkBadDomainName($err, $domain, $separator);
            $this->checkAncestorContexts($err, $domain, $separator);
            $this->checkLowercase($err, $domain);

            $this->trackDuplicate($domain, $state);
            $this->trackContradiction($domain, $state);
        }

        $this->reportStatefulErrors($err, $state);
    }

    /**
     * Check if the given domain is empty.
     *
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param list<string> $domains
     */
    private function checkEmptyDomain($err, array $domains, int $index): bool
    {
        $domain = trim($domains[$index]);

        if ($domain !== '') {
            return false;
        }

        $prev = isset($domains[$index - 1]) ? trim($domains[$index - 1]) : null;
        $next = isset($domains[$index + 1]) ? trim($domains[$index + 1]) : null;

        $context = '';

        if ($prev !== null && $prev !== '' && $next !== null && $next !== '') {
            $context = sprintf('between "%s" and "%s"', $prev, $next);
        } elseif ($prev !== null && $prev !== '') {
            $context = sprintf('after "%s"', $prev);
        } elseif ($next !== null && $next !== '') {
            $context = sprintf('before "%s"', $next);
        }

        $err->message(sprintf('Unexpected empty domain %s', $context))
            ->build();

        return true;
    }

    /**
     * Check if the domain name is bad.
     *
     * rNames:
     * - no-invalid-domains
     *
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function checkBadDomainName($err, string $domain, string $separator): void
    {
        if (strlen($domain) === 1
            && (($domain == '*' && $separator === '|') || $domain !== '*')
        ) {
            $err->message(sprintf('Bad domain name: "%s"', $domain))
                ->build();
        }

        if (!str_contains($domain, ' ')
            && (str_ends_with($domain, '.') && !preg_match('/^[\d\.]+$/', $domain)
                || str_starts_with($domain, '.')
                || str_contains($domain, '/'))
        ) {
            $err->message(sprintf('Bad domain name: "%s"', $domain))
                ->tip(sprintf('Did you mean "%s"?', $domain.'*'))
                ->build();
        }

        if (preg_match('/\s/', $domain)) {
            $err->message(sprintf(
                'Bad domain name: "%s" contains unnecessary whitespace.',
                $domain,
            ))->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function checkAncestorContexts($err, string $domain, string $separator): void
    {
        if (!str_ends_with($domain, '>')) {
            return;
        }

        if ($separator === '|') {
            $err->message(sprintf(
                'Bad domain name: "%s". The network filter does not support ancestor context.',
                $domain,
            ))->build();

            return;
        }

        preg_match('/([^>]+)([>]+)/', $domain, $m);

        if (strlen($m[2]) !== 2) {
            $err->message(sprintf('Bad domain name: "%s"', $domain))
                ->tip(sprintf('Did you mean "%s"?', $m[1].'>>'))
                ->build();
        }
    }

    /**
     * Check if the domain is lowercase.
     *
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     */
    private function checkLowercase($err, string $domain): void
    {
        if (!$this->config->rules['domain_case']) {
            return;
        }

        if (strtolower($domain) !== $domain) {
            $err->message(sprintf('Domain %s must be lowercase.', $domain))
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
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param array<string, mixed> $state The state array to modify.
     */
    private function reportStatefulErrors($err, array $state): void
    {
        foreach (array_unique($state['duplicates']) as $dup) {
            $err->message(sprintf('Duplicate domain: %s', $dup))
                ->build();
        }

        foreach (array_unique($state['contradictions']) as $cntr) {
            $err->message(sprintf('Contradictory domain %s detected.', $cntr))
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
