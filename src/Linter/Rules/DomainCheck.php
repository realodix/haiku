<?php

namespace Realodix\Haiku\Linter\Rules;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\Registry;
use Realodix\Haiku\Support\Tld;
use Realodix\Haiku\Support\Util;

/**
 * @phpstan-type _DomainState array{
 *  seen: array<string, bool>,
 *  duplicates: list<string>,
 *  inclusions: array<string, bool>,
 *  exclusions: array<string, bool>,
 *  conflicts: array<int, list<string>>,
 * }
 */
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
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     * @param ','|'|' $separator Domain separator (`,` or `|`)
     */
    private function validateDomains($err, string $domainStr, string $separator): void
    {
        if ($this->containsRegexDomain($domainStr)) {
            return;
        }

        $domainStr = $separator === '|' ? preg_replace('/,[_]+$/', '', $domainStr) : $domainStr; // remove noop option
        $domains = explode($separator, $domainStr);

        if (count($domains) > 1 && count(array_filter($domains, fn($d) => trim($d) !== '')) === 0) {
            $err->message('Invalid filter.')
                ->identifier('domain.empty')
                ->build();

            return;
        }

        /** @var _DomainState */
        $state = [
            'seen' => [],
            'duplicates' => [],
            'inclusions' => [],
            'exclusions' => [],
            'conflicts' => [],
        ];

        foreach ($domains as $index => $domain) {
            if ($this->checkEmptyDomain($err, $domains, $index)) {
                continue;
            }

            $this->checkBadDomainName($err, $domain, $separator);
            $this->checkAncestorContexts($err, $domain, $separator);
            $this->trackDuplicate($domain, $state);
            $this->trackDomainConflict($domain, $state);
        }

        $this->reportStatefulErrors($err, $state);
    }

    /**
     * Check if the given domain is empty.
     *
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
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

        $err->message("Unexpected empty domain {$context}")
            ->identifier('domain.empty')
            ->build();

        return true;
    }

    /**
     * Check if the domain name is bad.
     *
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     */
    private function checkBadDomainName($err, string $domain, string $separator): void
    {
        if ($this->config->rules['no_uppercase_domains'] && strtolower($domain) !== $domain) {
            $err->message("Domain \"{$domain}\" must be lowercase.")
                ->identifier('domain.case')
                ->build();
        }

        if (!$this->config->rules['no_bad_domains']) {
            return;
        }

        $whitelist = [
            'chrome-extension-scheme', 'moz-extension-scheme', 'addons.about-scheme',
            'localhost', 'local', 'dotblocking.dummy', 'parked.domain',
        ];
        if (in_array(ltrim($domain, '~'), $whitelist)) {
            return;
        }

        $domain = strtolower($domain);

        // =================================================================
        // Single character check
        // =================================================================
        if (strlen($domain) === 1
            && (($domain == '*' && $separator === '|') || $domain !== '*')
        ) {
            $err->message("Bad domain: \"{$domain}\"")
                ->identifier('domain.singleChar')
                ->build();

            return;
        }

        // =================================================================
        // Whitespace check
        // =================================================================
        if (preg_match('/\s/', $domain)) {
            $err->message("Bad domain: \"{$domain}\" must not contain whitespace.")
                ->identifier('domain.whitespace')
                ->build();

            return;
        }

        // =================================================================
        // Format / forbidden character check
        // =================================================================
        $dIdnaAscii = idn_to_ascii($domain);
        if ($dIdnaAscii === false) {
            $err->message("Bad domain: \"{$domain}\"")
                ->identifier('domain.malformed')
                ->build();

            return;
        }

        if (str_ends_with($domain, '.') && !preg_match('/^[\d\.]+$/', $domain)
            || str_starts_with($domain, '.')
            || str_contains($domain, '/')
        ) {
            $err->message("Bad domain: \"{$domain}\"")
                ->identifier('domain.malformed')
                ->build();

            return;
        }

        $domain = rtrim($dIdnaAscii, '>'); // clean up the ancestor context

        // missplaced wildcard
        if (str_contains($domain, '*') && !str_ends_with($domain, '*')) {
            $err->message("Bad domain: \"{$domain}\" has a wildcard in an invalid position.")
                ->identifier('domain.misplacedWildcard')
                ->build();
        }

        // =================================================================
        // TLD problems
        // =================================================================
        if (preg_match('/^[a-z0-9\-]+$/i', $domain) && !ctype_alpha($domain) && !str_starts_with($domain, 'xn--')) {
            $err->message("Bad domain: \"{$domain}\"")
                ->identifier('domain.malformed')
                ->build();
        }

        if (ctype_alpha($domain) || (str_starts_with($domain, 'xn--') && !str_contains($domain, '.'))) {
            if (!isset(Tld::VALUES[$domain])) {
                $msg = strlen($domain) <= 4 ?
                    "Bad domain: \"{$domain}\" is an invalid TLD."
                    : "Bad domain: \"{$domain}\"";

                $err->message($msg)
                    ->identifier('domain.invalid')
                    ->build();
            }
        }

        if (str_contains($domain, '.')
            && !(preg_match('/\.[\d]{1,3}+$/', $domain) || str_ends_with($domain, '.'))
        ) {
            $domainInfo = pathinfo($domain);
            $tld = $domainInfo['extension'];

            if (!isset(Tld::VALUES[$tld]) && $tld !== '*') {
                $hint = Util::getSuggestion(array_keys(Tld::VALUES), $tld);
                $err->message("Bad domain: \"{$domain}\" has an invalid TLD.")
                    ->identifier('domain.invalid')
                    ->when($hint, function () use ($err, $hint, $domainInfo) {
                        $err->tip(sprintf('Did you mean "%s"?', $domainInfo['filename'].'.'.$hint));
                    })->build();
            }
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     */
    private function checkAncestorContexts($err, string $domain, string $separator): void
    {
        if (!str_ends_with($domain, '>')) {
            return;
        }

        if ($separator === '|') {
            $err->message("Bad domain: \"{$domain}\". The network filter does not support ancestor context.")
                ->identifier('domain.invalidAncestorContext')
                ->build();

            return;
        }

        preg_match('/([^>]+)([>]+)/', $domain, $m);

        if (isset($m[2]) && strlen($m[2]) !== 2) {
            $err->message("Bad domain: \"{$domain}\"")
                ->identifier('domain.invalidAncestorContext')
                ->tip(sprintf('Did you mean "%s"?', $m[1].'>>'))
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
     * @param _DomainState $state The state array to modify.
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
     * Tracks domain conflicts.
     *
     * If the domain is negated (~domain), it is checked against the list of inclusions.
     * If the domain is not negated, it is checked against the list of exclusions.
     * If an included domain is covered by an excluded domain, a conflict is recorded.
     * Otherwise, the domain is marked as either included or excluded.
     *
     * @param string $domain The domain to track.
     * @param _DomainState $state The state array to modify.
     */
    private function trackDomainConflict(string $domain, array &$state): void
    {
        if (!$this->config->rules['no_conflict_domains']) {
            return;
        }

        $isNegated = str_starts_with($domain, '~');
        $domain = ltrim($domain, '~');

        if ($isNegated) {
            foreach ($state['inclusions'] as $includedDomain => $_) {
                if ($this->domainCovers($domain, $includedDomain)) {
                    $state['conflicts'][] = [$includedDomain, '~'.$domain];
                }
            }

            $state['exclusions'][$domain] = true;

            return;
        }

        foreach ($state['exclusions'] as $excludedDomain => $_) {
            if ($this->domainCovers($excludedDomain, $domain)) {
                $state['conflicts'][] = [$domain, '~'.$excludedDomain];
            }
        }

        $state['inclusions'][$domain] = true;
    }

    /**
     * Reports any duplicate or contradictory domains found during analysis.
     *
     * This function will iterate through the state array and report any duplicate
     * or contradictory domains found.
     *
     * @param \Realodix\Haiku\Linter\ErrorBuilder $err
     * @param _DomainState $state The state array to modify.
     */
    private function reportStatefulErrors($err, array $state): void
    {
        foreach (array_unique($state['duplicates']) as $dup) {
            $err->message("Duplicate domain: {$dup}")
                ->identifier('domain.duplicate')
                ->build();
        }

        foreach ($state['conflicts'] as [$includedDomain, $excludedDomain]) {
            $err->message("Domain conflict: \"{$includedDomain}\" and \"{$excludedDomain}\"")
                ->identifier('domain.conflict')
                ->build();
        }
    }

    private function domainCovers(string $covering, string $target): bool
    {
        if ($covering === $target) {
            return true;
        }

        if (str_ends_with($covering, '.*')) {
            $prefix = substr($covering, 0, -2);

            return str_starts_with($target, $prefix.'.');
        }

        return false;
    }

    private function containsRegexDomain(string $domainStr): bool
    {
        $domainStr = trim($domainStr);

        return (str_starts_with($domainStr, '/') && str_ends_with($domainStr, '/'))
            || (str_contains($domainStr, '/') && preg_match('/[\\^([{$\\\]/', $domainStr));
    }
}
