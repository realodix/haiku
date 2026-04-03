<?php

namespace Realodix\Haiku\Linter\Rules;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Util;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 */
final class DomainCheck implements Rule
{
    const DOMAIN = ['domain', 'from', 'to', 'denyallow'];

    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        $errors = [];

        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
            $line = trim($line);

            if (Util::isCommentOrEmpty($line)) {
                continue;
            }

            // Cosmetic rule
            if (preg_match(Regex::COSMETIC_DOMAIN, $line, $m)) {
                $this->validateDomains($errors, $lineNum, $m[1], ',', 'cosmetic');
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

                    if (in_array($name, self::DOMAIN, true)) {
                        $this->validateDomains($errors, $lineNum, $value, '|', 'network');
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<_RuleError> $errors
     */
    private function validateDomains(
        array &$errors,
        int $lineNum,
        string $domainStr,
        string $separator,
        string $type,
    ): void {
        if ($this->containsRegexDomain($domainStr)) {
            return;
        }

        if ($type === 'cosmetic' && trim($domainStr) === '') {
            return;
        }

        $domains = explode($separator, $domainStr);

        $state = [
            'seen' => [],
            'duplicates' => [],
            'inclusions' => [],
            'exclusions' => [],
            'contradictions' => [],
        ];

        foreach ($domains as $domain) {
            if ($err = $this->checkEmptyDomain($lineNum, $domain, $type)) {
                $errors[] = $err;

                continue;
            }

            $this->checkBadDomainName($errors, $lineNum, $domain);
            $this->checkLowercase($errors, $lineNum, $domain);

            $this->trackDuplicate($domain, $state);
            $isNegated = str_starts_with($domain, '~');
            $cleanDomain = ltrim($domain, '~');
            $this->trackContradiction($isNegated, $cleanDomain, $state);
        }

        $this->reportStatefulErrors($errors, $lineNum, $state);
    }

    /**
     * Check if the given domain is empty.
     *
     * @return _RuleError|null
     */
    private function checkEmptyDomain(int $lineNum, string $domain, string $type)
    {
        $domain = trim($domain);

        if ($domain !== '') {
            return null;
        }

        $msg = $type === 'cosmetic'
            ? 'Unexpected empty domain in cosmetic rule.'
            : 'Unexpected empty domain in network filter.';

        return RuleErrorBuilder::message($msg)
            ->line($lineNum)
            ->build();
    }

    /**
     * Check if the domain name is bad.
     *
     * @param list<_RuleError> $errors
     */
    private function checkBadDomainName(array &$errors, int $lineNum, string $domain): void
    {
        if (strlen($domain) < 2 && $domain !== '*') {
            $errors[] = RuleErrorBuilder::message(sprintf('Bad domain name: "%s"', $domain))
                ->line($lineNum)
                ->build();
        }

        if (str_ends_with($domain, '.') && !preg_match('/^[\d\.]+$/', $domain)
            || str_starts_with($domain, '.')
            || str_contains($domain, '/')
        ) {
            $errors[] = RuleErrorBuilder::message(sprintf('Bad domain name: "%s"', $domain))
                ->tip(sprintf('Did you mean "%s"?', $domain.'*'))
                ->line($lineNum)
                ->build();
        }

        if (preg_match('/\s/', $domain)) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Bad domain name: "%s" contains unnecessary whitespace.', $domain),
            )->line($lineNum)->build();
        }
    }

    /**
     * Check if the domain is lowercase.
     *
     * @param list<_RuleError> $errors
     */
    private function checkLowercase(array &$errors, int $lineNum, string $domain): void
    {
        if (!$this->config->rules['lowercase_domains']) {
            return;
        }

        if (strtolower($domain) !== $domain) {
            $errors[] = RuleErrorBuilder::message(sprintf('Domain %s must be lowercase.', $domain))
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
     * @param bool $isNegated Whether the domain is negated.
     * @param string $domain The domain to track.
     * @param array<string, mixed> $state The state array to modify.
     */
    private function trackContradiction(bool $isNegated, string $domain, array &$state): void
    {
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
     * @param list<_RuleError> $errors The list of errors to add to.
     * @param int $lineNum The line number where the errors were found.
     * @param array<string, mixed> $state The state array to modify.
     */
    private function reportStatefulErrors(array &$errors, int $lineNum, array $state): void
    {
        foreach (array_unique($state['duplicates']) as $dup) {
            $errors[] = RuleErrorBuilder::message(sprintf('Duplicate domain "%s".', $dup))
                ->line($lineNum)
                ->build();
        }

        foreach (array_unique($state['contradictions']) as $cntr) {
            $errors[] = RuleErrorBuilder::message(sprintf('Contradictory domain %s detected.', $cntr))
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
