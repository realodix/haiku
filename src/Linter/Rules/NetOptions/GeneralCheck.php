<?php

namespace Realodix\Haiku\Linter\Rules\NetOptions;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Linter\Util;

final class GeneralCheck implements Rule
{
    private const ALIASES = [
        'from' => 'domain',
        '1p' => 'first-party',
        '3p' => 'third-party',
        'strict1p' => 'strict-first-party',
        'strict3p' => 'strict-third-party',
        'css' => 'stylesheet',
        'doc' => 'document',
        'ehide' => 'elemhide',
        'frame' => 'subdocument',
        'ghide' => 'generichide',
        'shide' => 'specifichide',
        'xhr' => 'xmlhttprequest',
    ];

    public function __construct(
        private LinterConfig $config,
    ) {}

    public function check(array $content): array
    {
        $err = new RuleErrorBuilder;

        foreach ($content as $index => $line) {
            $err->line($index + 1);
            $line = trim($line);

            if (Util::isCommentOrEmpty($line)) {
                continue;
            }

            if (!preg_match(Regex::NET_OPTION, $line, $m)
                || preg_match(Regex::IS_COSMETIC_RULE, $line)
                || str_contains($line, 'replace=')
            ) {
                continue;
            }

            $rawOpts = Util::splitOptions($m[2]);

            $this->checkDuplicateOptions($err, $rawOpts);
            $this->checkOptionConflict($err, $rawOpts);
            $this->checkOptionsCase($err, $rawOpts);
            $this->checkInvalidNegation($err, $rawOpts);

            $opts = $this->parseOptions($rawOpts);

            $this->checkOptionAliasRedundant($err, $opts);
            $this->checkDeprecatedOptions($err, $opts);
            $this->checkExceptionOptions($err, $opts, $line);
            $this->checkInterOptionDomainContradiction($err, $opts);
            $this->checkDenyallowValue($err, $opts);
            $this->checkDenyallowAndToConflict($err, $opts);
            $this->checkDenyallowRequiresDomain($err, $opts);
        }

        return $err->toArray();
    }

    /**
     * @param list<string> $opts
     * @return array<string, list<string|null>>
     */
    private function parseOptions(array $opts): array
    {
        $map = [];

        foreach ($opts as $opt) {
            $opt = trim($opt);

            $parts = explode('=', $opt, 2);
            $name = strtolower(trim($parts[0]));
            $value = $parts[1] ?? null;

            $map[$name][] = $value;
        }

        return $map;
    }

    /**
     * @param list<string> $opts
     */
    private function checkDuplicateOptions(RuleErrorBuilder $err, array $opts): void
    {
        if (!$this->config->rules['no_dupe_options']) {
            return;
        }

        $seen = [];
        $duplicates = [];

        foreach ($opts as $opt) {
            $opt = trim($opt);

            $parts = explode('=', $opt, 2);
            $name = strtolower(trim($parts[0]));

            if (isset($seen[$name])) {
                $duplicates[] = $name;
            }

            $seen[$name] = true;
        }

        foreach (array_unique($duplicates) as $dup) {
            $err->message(sprintf('Duplicate option: $%s', $dup))
                ->build();
        }
    }

    /**
     * @param list<string> $opts
     */
    private function checkOptionsCase(RuleErrorBuilder $err, array $opts): void
    {
        foreach ($opts as $opt) {
            $opt = trim($opt);

            $parts = explode('=', $opt, 2);
            $rawName = trim($parts[0]);
            $name = strtolower($rawName);

            if ($rawName !== $name) {
                $err->message(sprintf('Option "%s" must be lowercase.', $rawName))
                    ->build();
            }
        }
    }

    /**
     * rNames:
     * - no-option-conflict
     *
     * @param list<string> $rawOpts
     */
    private function checkOptionConflict(RuleErrorBuilder $err, array $rawOpts): void
    {
        $positive = [];
        $negative = [];

        foreach ($rawOpts as $opt) {
            $opt = trim($opt);

            $parts = explode('=', $opt, 2);
            $rawName = trim($parts[0]);

            if ($rawName === '') {
                continue;
            }

            if (str_starts_with($rawName, '~')) {
                $name = substr($rawName, 1);
                $negative[] = strtolower($name);
            } else {
                $positive[] = strtolower($rawName);
            }
        }

        // Normalize aliases
        $normalize = function (string $opt): string {
            return self::ALIASES[$opt] ?? $opt;
        };

        $positiveNorm = array_map($normalize, $positive);
        $negativeNorm = array_map($normalize, $negative);

        $conflicts = array_intersect($positiveNorm, $negativeNorm);

        if ($conflicts === []) {
            return;
        }

        foreach (array_unique($conflicts) as $conflict) {
            $err->message(sprintf('$%s conflicts with its negation.', $conflict))
                ->build();
        }
    }

    /**
     * rNames:
     * - no-invalid-negated-option
     * - no-invalid-option-negation
     *
     * @param list<string> $rawOpts
     */
    private function checkInvalidNegation(RuleErrorBuilder $err, array $rawOpts): void
    {
        foreach ($rawOpts as $opt) {
            $opt = trim($opt);

            if (!str_starts_with($opt, '~')) {
                continue;
            }

            $hasValue = str_contains($opt, '=');
            $name = substr($opt, 1);
            if ($hasValue) {
                $name = strstr($name, '=', true);
            }

            if ($this->isNegatableOption($name, $hasValue)) {
                continue;
            }

            $err->message(sprintf('$%s cannot be negated.', $name))
                ->build();
        }
    }

    /**
     * @param array<string, list<string|null>> $opts
     */
    private function checkOptionAliasRedundant(RuleErrorBuilder $err, array $opts): void
    {
        foreach (self::ALIASES as $alias => $canonical) {
            if (isset($opts[$alias]) && isset($opts[$canonical])) {
                $msg = sprintf(
                    'Duplicate option: $%s and $%s are aliases of each other.',
                    $alias, $canonical,
                );
                $err->message($msg)->build();
            }
        }
    }

    /**
     * @param array<string, list<string|null>> $opts
     */
    private function checkDeprecatedOptions(RuleErrorBuilder $err, array $opts): void
    {
        $depOpts = [
            'empty' => null, 'mp4' => null, 'webrtc' => null,
            'object-subrequest' => 'object',
            'queryprune' => 'removeparam',
        ];

        foreach ($depOpts as $opt => $replacement) {
            if (!array_key_exists($opt, $opts)) {
                continue;
            }

            $err->message(sprintf('Deprecated filter option: $%s', $opt));

            if ($replacement !== null) {
                $err->tip(sprintf('Use "%s" instead.', $replacement));
            }

            $err->build();
        }
    }

    /**
     * rNames:
     * - no-invalid-exception-options
     * - no-invalid-exception-rules
     *
     * @param array<string, list<string|null>> $opts
     */
    private function checkExceptionOptions(RuleErrorBuilder $err, array $opts, string $lineContent): void
    {
        $isException = str_starts_with($lineContent, '@@');

        // 1. Must NOT be used in exception rules
        $blockOnly = ['important', 'empty', 'mp4'];
        foreach ($blockOnly as $opt) {
            if ($isException && array_key_exists($opt, $opts)) {
                $err->message(sprintf(
                    'Invalid filter: $%s is not allowed in exception rules.',
                    $opt,
                ))->build();
            }
        }

        // 2. Options that REQUIRE exception rule when they have no value
        // - With value -> allowed anywhere
        // - Without value -> only allowed in exception rules
        $requiresExceptionIfNoValue = [
            'csp', 'permissions', 'redirect', 'redirect-rule', 'replace',
            'uritransform', 'urlskip',
        ];

        foreach ($requiresExceptionIfNoValue as $opt) {
            if (!array_key_exists($opt, $opts)) {
                continue;
            }

            foreach ($opts[$opt] as $value) {
                // If the option has a value -> always valid
                if ($value !== null && $value !== '') {
                    continue;
                }

                // If no value -> must be used in an exception rule
                if (!$isException) {
                    $err->message(sprintf(
                        'Invalid filter: $%s without value is only allowed in exception rules.',
                        $opt,
                    ))->build();
                }
            }
        }

        // 3. Options that are ONLY allowed in exception rules
        $exceptionOnly = [
            'cname',
            'genericblock',
        ];

        foreach ($exceptionOnly as $opt) {
            if (array_key_exists($opt, $opts) && !$isException) {
                $err->message(sprintf(
                    'Invalid filter: $%s is only allowed in exception rules.',
                    $opt,
                ))->build();
            }
        }
    }

    /**
     * rNames:
     * - no-inter-option-domain-contradiction
     * - no-cross-option-domain-conflict
     * - no-domain-conflict-between-options
     *
     * @param array<string, list<string|null>> $opts
     */
    private function checkInterOptionDomainContradiction(RuleErrorBuilder $err, array $opts): void
    {
        $domain = $this->parseDomainList($opts['domain'] ?? null);
        $from = $this->parseDomainList($opts['from'] ?? null);
        $base = array_unique([...$domain, ...$from]);
        $contradictor = isset($opts['domain']) ? '$domain' : '$from';

        if ($base === []) {
            return;
        }

        // $denyallow
        if (isset($opts['denyallow']) && !(isset($opts['3p']) || isset($opts['third-party']))) {
            $deny = $this->parseDomainList($opts['denyallow']);

            $overlap = array_intersect($base, $deny);
            if ($overlap !== []) {
                $err->message(sprintf(
                    "Option \$denyallow contradicts {$contradictor} for: %s",
                    implode(', ', $overlap),
                ))->build();
            }
        }

        // $to
        if (isset($opts['to'])) {
            $toDomains = $this->parseDomainList($opts['to']);

            $toExclude = [];
            foreach ($toDomains as $d) {
                if (str_starts_with($d, '~')) {
                    $toExclude[] = substr($d, 1);
                }
            }

            $conflicts = array_intersect($base, $toExclude);

            if ($conflicts !== []) {
                $err->message(sprintf(
                    "Option \$to contradicts {$contradictor} for: %s",
                    implode(', ', $conflicts),
                ))->build();
            }
        }
    }

    /**
     * @param array<string, list<string|null>> $opts
     */
    private function checkDenyallowValue(RuleErrorBuilder $err, array $opts): void
    {
        if (!isset($opts['denyallow'])) {
            return;
        }

        foreach ($opts['denyallow'] as $value) {
            if ($value === null) {
                continue;
            }

            foreach (explode('|', $value) as $domain) {
                $domain = trim($domain);
                if ($domain === '') {
                    continue;
                }

                if (str_starts_with($domain, '~')) {
                    $err->message(sprintf(
                        'Domains in the $denyallow value cannot be negated: "%s".',
                        $domain,
                    ))->build();
                }

                if (str_ends_with($domain, '.*')) {
                    $err->message(sprintf(
                        'Domains in the $denyallow value cannot have a wildcard TLD: "%s".',
                        $domain,
                    ))->build();
                }
            }
        }
    }

    /**
     * Checks $denyallow used together with $to
     *
     * @param array<string, list<string|null>> $opts
     */
    private function checkDenyallowAndToConflict(RuleErrorBuilder $err, array $opts): void
    {
        if (isset($opts['denyallow']) && isset($opts['to'])) {
            $err->message('Redundant usage of $denyallow with $to.')
                ->tip('It can be expressed with inverted $to: $denyallow=a.com is equivalent to $to=~a.com.')
                ->build();
        }
    }

    /**
     * @param array<string, list<string|null>> $opts
     */
    private function checkDenyallowRequiresDomain(RuleErrorBuilder $err, array $opts): void
    {
        if (isset($opts['denyallow'])
            && !isset($opts['domain'])
            && !isset($opts['from'])
        ) {
            $err->message('Invalid filter: $denyallow requires $domain.')
                ->build();
        }
    }

    private function isNegatableOption(string $name, bool $hasValue): bool
    {
        // Options with value cannot be negated
        if ($hasValue) {
            return false;
        }

        // Non-negatable options (explicit list)
        static $nonNegatable = [
            'all', 'cname', 'important', 'removeparam',
            'ehide', 'elemhide',
            'ghide', 'generichide',
            'shide', 'specifichide',
            'strict1p', 'strict3p',
            'empty', 'mp4', 'queryprune', 'genericblock',
        ];

        if (in_array($name, $nonNegatable, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param list<string|null>|null $values
     * @return array<string>
     */
    private function parseDomainList(?array $values): array
    {
        if ($values === null) {
            return [];
        }

        $domains = [];

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            foreach (explode('|', $value) as $d) {
                $d = trim($d);
                if ($d !== '') {
                    $domains[] = $d;
                }
            }
        }

        return array_unique($domains);
    }
}
