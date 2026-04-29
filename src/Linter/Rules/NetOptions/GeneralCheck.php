<?php

namespace Realodix\Haiku\Linter\Rules\NetOptions;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\RuleErrorBuilder;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Linter\Util;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 */
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
        $errors = [];

        foreach ($content as $index => $line) {
            $lineNum = $index + 1;
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

            $this->checkDuplicateOptions($errors, $lineNum, $rawOpts);
            $this->checkOptionConflict($errors, $lineNum, $rawOpts);
            $this->checkOptionsCase($errors, $lineNum, $rawOpts);
            $this->checkInvalidNegation($errors, $lineNum, $rawOpts);

            $opts = $this->parseOptions($rawOpts);

            $this->checkOptionAliasRedundant($errors, $lineNum, $opts);
            $this->checkDeprecatedOptions($errors, $lineNum, $opts);
            $this->checkExceptionOptions($errors, $lineNum, $opts, $line);
            $this->checkInterOptionDomainContradiction($errors, $lineNum, $opts);

            $this->checkDenyallowValue($errors, $lineNum, $opts);
            $this->checkDenyallowAndToConflict($errors, $lineNum, $opts);
            $this->checkDenyallowRequiresDomain($errors, $lineNum, $opts);
        }

        return $errors;
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
     * @param list<_RuleError> $errors
     * @param list<string> $opts
     */
    private function checkDuplicateOptions(array &$errors, int $lineNum, array $opts): void
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
            $errors[] = RuleErrorBuilder::message(sprintf('Duplicate option: "$%s".', $dup))
                ->line($lineNum)->build();
        }
    }

    /**
     * @param list<_RuleError> $errors
     * @param list<string> $opts
     */
    private function checkOptionsCase(array &$errors, int $lineNum, array $opts): void
    {
        foreach ($opts as $opt) {
            $opt = trim($opt);

            $parts = explode('=', $opt, 2);
            $rawName = trim($parts[0]);
            $name = strtolower($rawName);

            if ($rawName !== $name) {
                $errors[] = RuleErrorBuilder::message(sprintf('Option "%s" must be lowercase.', $rawName))
                    ->line($lineNum)->build();
            }
        }
    }

    /**
     * rNames:
     * - no-option-conflict
     *
     * @param list<_RuleError> $errors
     * @param list<string> $rawOpts
     */
    private function checkOptionConflict(array &$errors, int $lineNum, array $rawOpts): void
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
            $errors[] = RuleErrorBuilder::message(sprintf('$%s conflicts with its negation.', $conflict))
                ->line($lineNum)
                ->build();
        }
    }

    /**
     * rNames:
     * - no-invalid-negated-option
     * - no-invalid-option-negation
     *
     * @param list<_RuleError> $errors
     * @param list<string> $rawOpts
     */
    private function checkInvalidNegation(array &$errors, int $lineNum, array $rawOpts): void
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

            $errors[] = RuleErrorBuilder::message(sprintf('$%s cannot be negated.', $name))
                ->line($lineNum)
                ->build();
        }
    }

    /**
     * @param list<_RuleError> $errors
     * @param array<string, list<string|null>> $opts
     */
    private function checkOptionAliasRedundant(array &$errors, int $lineNum, array $opts): void
    {
        foreach (self::ALIASES as $alias => $canonical) {
            if (isset($opts[$alias]) && isset($opts[$canonical])) {
                $msg = sprintf(
                    'Duplicate option:: $%s and $%s are aliases of each other.',
                    $alias,
                    $canonical,
                );
                $errors[] = RuleErrorBuilder::message($msg)->line($lineNum)->build();
            }
        }
    }

    /**
     * @param list<_RuleError> $errors
     * @param array<string, list<string|null>> $opts
     */
    private function checkDeprecatedOptions(array &$errors, int $lineNum, array $opts): void
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

            $builder = RuleErrorBuilder::message(sprintf('Deprecated filter option: "$%s".', $opt))
                ->line($lineNum);

            if ($replacement !== null) {
                $builder->tip(sprintf('Use "%s" instead.', $replacement));
            }

            $errors[] = $builder->build();
        }
    }

    /**
     * rNames:
     * - no-invalid-exception-options
     * - no-invalid-exception-rules
     *
     * @param list<_RuleError> $errors
     * @param array<string, list<string|null>> $opts
     */
    private function checkExceptionOptions(array &$errors, int $lineNum, array $opts, string $lineContent): void
    {
        $isException = str_starts_with($lineContent, '@@');

        // 1. Must NOT be used in exception rules
        $blockOnly = ['important', 'empty', 'mp4'];
        foreach ($blockOnly as $opt) {
            if ($isException && array_key_exists($opt, $opts)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Invalid filter: $%s is not allowed in exception rules.',
                    $opt,
                ))->line($lineNum)->build();
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
                // If the option has a value → always valid
                if ($value !== null && $value !== '') {
                    continue;
                }

                // If no value → must be used in an exception rule
                if (!$isException) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Invalid filter: $%s without value is only allowed in exception rules.',
                        $opt,
                    ))->line($lineNum)->build();
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
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Invalid filter: $%s is only allowed in exception rules.',
                    $opt,
                ))->line($lineNum)->build();
            }
        }
    }

    /**
     * rNames:
     * - no-inter-option-domain-contradiction
     * - no-cross-option-domain-conflict
     * - no-domain-conflict-between-options
     *
     * @param list<_RuleError> $errors
     * @param array<string, list<string|null>> $opts
     */
    private function checkInterOptionDomainContradiction(array &$errors, int $lineNum, array $opts): void
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
                $errors[] = RuleErrorBuilder::message(sprintf(
                    "Option \$denyallow contradicts {$contradictor} for: %s",
                    implode(', ', $overlap),
                ))->line($lineNum)->build();
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
                $errors[] = RuleErrorBuilder::message(sprintf(
                    "Option \$to contradicts {$contradictor} for: %s",
                    implode(', ', $conflicts),
                ))->line($lineNum)->build();
            }
        }
    }

    /**
     * @param list<_RuleError> $errors
     * @param array<string, list<string|null>> $opts
     */
    private function checkDenyallowValue(array &$errors, int $lineNum, array $opts): void
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
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Domains in the $denyallow value cannot be negated: "%s".',
                        $domain,
                    ))->line($lineNum)->build();
                }

                if (str_ends_with($domain, '.*')) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Domains in the $denyallow value cannot have a wildcard TLD: "%s".',
                        $domain,
                    ))->line($lineNum)->build();
                }
            }
        }
    }

    /**
     * Checks $denyallow used together with $to
     *
     * @param list<_RuleError> $errors
     * @param array<string, list<string|null>> $opts
     */
    private function checkDenyallowAndToConflict(array &$errors, int $lineNum, array $opts): void
    {
        if (isset($opts['denyallow']) && isset($opts['to'])) {
            $errors[] = RuleErrorBuilder::message('Redundant usage of $denyallow with $to.')
                ->line($lineNum)
                ->tip('It can be expressed with inverted $to: $denyallow=a.com is equivalent to $to=~a.com.')
                ->build();
        }
    }

    /**
     * @param list<_RuleError> $errors
     * @param array<string, list<string|null>> $opts
     */
    private function checkDenyallowRequiresDomain(array &$errors, int $lineNum, array $opts): void
    {
        if (isset($opts['denyallow'])
            && !isset($opts['domain'])
            && !isset($opts['from'])
        ) {
            $errors[] = RuleErrorBuilder::message('Invalid filter: $denyallow requires $domain.')
                ->line($lineNum)
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
