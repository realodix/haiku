<?php

namespace Realodix\Haiku\Linter\Rules\NetOptions;

use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Fixer\Regex;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Support\Util;

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

    public function check(array $content, $err): array
    {
        foreach ($content as $index => $line) {
            $err->line($index + 1);
            $line = trim($line);

            if (Util::isCommentOrEmpty($line)
                || preg_match(Regex::IS_COSMETIC_RULE, $line)
                || str_contains($line, 'replace=')
            ) {
                continue;
            }

            if (preg_match('/(?<=[\^,\$])domain=(?=[a-z0-9])/', $line, $m, PREG_OFFSET_CAPTURE)) {
                $position = $m[0][1];
                $before = substr($line, 0, $position);
                $optionPosition = strpos($before, '$');

                if ($optionPosition === false) {
                    $err->message('Possibly missing "$" at the start of filter options.')
                        ->build();
                } else {
                    for ($i = $optionPosition + 1, $length = strlen($before); $i < $length; $i++) {
                        if ($before[$i] === '/') {
                            break;
                        }

                        if ($before[$i] === '\\') {
                            $i++;

                            continue;
                        }

                        if ($before[$i] === '$') {
                            $err->message('Possibly multiple "$" separators in filter options.')
                                ->build();

                            break;
                        }
                    }
                }
            }

            if (!preg_match(Regex::NET_OPTION, $line, $m)) {
                continue;
            }

            $rawOpts = Util::splitOptions($m[2]);

            $this->checkCase($err, $rawOpts);
            $this->checkDuplicate($err, $rawOpts);
            $this->checkDuplicateWithNegation($err, $rawOpts);
            $this->checkInvalidNegation($err, $rawOpts);

            $opts = $this->parseOptions($rawOpts);

            $this->checkDuplicateWithAlias($err, $opts);
            $this->checkInvalidException($err, $opts, $line);
            $this->checkWithoutValueExceptionOnly($err, $opts, $line);
            $this->checkDenyallowValue($err, $opts);
            $this->checkDenyallowAndToConflict($err, $opts);
            $this->checkDenyallowRequiresDomain($err, $opts);

            $this->checkDeprecatedOptions($err, $opts);
        }

        return $err->toArray();
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param list<string> $opts
     */
    private function checkCase($err, array $opts): void
    {
        foreach ($opts as $opt) {
            $opt = trim($opt);

            $parts = explode('=', $opt, 2);
            $rawName = trim($parts[0]);
            $name = strtolower($rawName);

            if ($rawName !== $name) {
                $err->message("Option \"{$rawName}\" must be lowercase.")
                    ->build();
            }
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param list<string> $opts
     */
    private function checkDuplicate($err, array $opts): void
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
            $err->message("Duplicate option: \${$dup}")
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param list<string> $rawOpts
     */
    private function checkDuplicateWithNegation($err, array $rawOpts): void
    {
        if (!$this->config->rules['no_dupe_options']) {
            return;
        }

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
            $err->message("\${$conflict} conflicts with its negation.")
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param array<string, list<string|null>> $opts
     */
    private function checkDuplicateWithAlias($err, array $opts): void
    {
        if (!$this->config->rules['no_dupe_options']) {
            return;
        }

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
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param list<string> $rawOpts
     */
    private function checkInvalidNegation($err, array $rawOpts): void
    {
        if (!$this->config->rules['no_unsupported_option_negation']) {
            return;
        }

        foreach ($rawOpts as $opt) {
            $opt = trim($opt);

            if (!str_starts_with($opt, '~')) {
                continue;
            }

            $hasValue = str_contains($opt, '=');
            $name = substr($opt, 1);
            if ($hasValue) {
                $name = strstr($name, '=', true);
                if ($name === false) {
                    continue;
                }
            }

            if ($this->isNegatableOption($name, $hasValue)) {
                continue;
            }

            $err->message("\${$name} cannot be negated.")
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param array<string, list<string|null>> $opts
     */
    private function checkInvalidException($err, array $opts, string $lineContent): void
    {
        if (!$this->config->rules['no_invalid_option_context']) {
            return;
        }

        $isException = str_starts_with($lineContent, '@@');

        // 1. Must NOT be used in exception rules
        $blockOnly = ['important', 'empty', 'mp4'];
        $foundInvalid = array_find($blockOnly, fn($opt) => $isException && array_key_exists($opt, $opts));
        if ($foundInvalid) {
            $err->message("Invalid filter: \${$foundInvalid} is not allowed in exception rules.")
                ->build();
        }

        // 2. Options that are ONLY allowed in exception rules
        $exceptionOnly = [
            'cname',
            'genericblock',
        ];

        foreach ($exceptionOnly as $opt) {
            if (array_key_exists($opt, $opts) && !$isException) {
                $err->message("Invalid filter: \${$opt} is only allowed in exception rules.")
                    ->build();
            }
        }
    }

    /**
     * Check that valueless options are only used in exception rules.
     *
     * Condition:
     * - With value -> allowed anywhere.
     * - Without value -> only allowed in exception rules.
     *
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param array<string, list<string|null>> $opts
     */
    private function checkWithoutValueExceptionOnly($err, array $opts, string $lineContent): void
    {
        $isException = str_starts_with($lineContent, '@@');
        $reqExcIfNoValue = [
            'csp', 'permissions', 'redirect', 'redirect-rule', 'replace',
            'uritransform', 'urlskip',
            // AG
            'removeheader',
        ];

        foreach ($reqExcIfNoValue as $opt) {
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
                    $err->message("Invalid filter: \${$opt} without value is only allowed in exception rules.")
                        ->build();
                }
            }
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param array<string, list<string|null>> $opts
     */
    private function checkDenyallowValue($err, array $opts): void
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

                if (str_starts_with($domain, '~')) {
                    $err->message(sprintf(
                        'Domains in the $denyallow value cannot be negated: "%s"',
                        $domain,
                    ))->build();
                }

                if (str_ends_with($domain, '.*')) {
                    $err->message(sprintf(
                        'Domains in the $denyallow value cannot have a wildcard TLD: "%s"',
                        $domain,
                    ))->build();
                }
            }
        }
    }

    /**
     * Checks $denyallow used together with $to
     *
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param array<string, list<string|null>> $opts
     */
    private function checkDenyallowAndToConflict($err, array $opts): void
    {
        if (isset($opts['denyallow']) && isset($opts['to'])) {
            $err->message('$denyallow cannot be used together with $to.')
                ->tip('It can be expressed with inverted $to: $denyallow=a.com is equivalent to $to=~a.com.')
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param array<string, list<string|null>> $opts
     */
    private function checkDenyallowRequiresDomain($err, array $opts): void
    {
        if (isset($opts['denyallow'])
            && !isset($opts['domain'])
            && !isset($opts['from'])
        ) {
            $err->message('Invalid filter: $denyallow requires $domain.')
                ->build();
        }
    }

    /**
     * @param \Realodix\Haiku\Linter\RuleErrorBuilder $err
     * @param array<string, list<string|null>> $opts
     */
    private function checkDeprecatedOptions($err, array $opts): void
    {
        if (!$this->config->rules['no_deprecated_options']) {
            return;
        }

        $depOpts = [
            'empty' => null, 'mp4' => null, 'webrtc' => null,
            'object-subrequest' => 'object',
            'queryprune' => 'removeparam',
        ];

        foreach ($depOpts as $opt => $replacement) {
            if (!array_key_exists($opt, $opts)) {
                continue;
            }

            $err->message("Deprecated: The filter option \${$opt} is deprecated.");

            if ($replacement !== null) {
                $err->tip(sprintf('Use "%s" instead.', $replacement));
            }

            $err->build();
        }
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
}
