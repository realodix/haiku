<?php

namespace Realodix\Haiku\Config;

use Realodix\Haiku\Support\File;
use Symfony\Component\Filesystem\Path;

/**
 * @phpstan-type _LinterRules array{
 *  all?: bool,
 *  extra_blank_lines: false|int,
 *  if_directive_balance: bool,
 *  no_always_false_condition: bool,
 *  no_bad_domain_anchors: bool,
 *  no_bad_domains: bool,
 *  no_conflict_domains: bool,
 *  no_deprecated_options: bool,
 *  no_deprecated_redirect_resources: bool,
 *  no_deprecated_scriptlets: bool,
 *  no_dupe_domains: bool,
 *  no_dupe_options: bool,
 *  no_dupe_rules: bool,
 *  no_invalid_abp_extended_css_selectors: bool,
 *  no_invalid_id_selectors: bool,
 *  no_invalid_if_directive_values: bool,
 *  no_invalid_option_context: bool,
 *  no_invalid_redirect_resources: bool,
 *  no_invalid_scriptlets: bool|array{known: list<string>},
 *  no_short_rules: false|array{minLen: int},
 *  no_unsupported_option_negation: bool,
 *  no_uppercase_domains: bool,
 * }
 * @phpstan-type _ConfigIgnoredError array{
 *  message?: string,
 *  messages?: list<string>|string,
 *  path?: string,
 *  paths?: list<string>|string,
 *  identifier?: string,
 *  identifiers?: list<string>,
 * }|string
 */
final class LinterConfig
{
    /**
     * List of resolved absolute file paths to be processed
     *
     * @var array<int, string>
     */
    public private(set) array $paths;

    /** @var list<_ConfigIgnoredError> */
    public array $ignoreErrors = [];

    /** @var _LinterRules */
    public array $rules = [
        'extra_blank_lines' => false,
        'if_directive_balance' => false,
        'no_always_false_condition' => true,
        'no_bad_domain_anchors' => true,
        'no_bad_domains' => true,
        'no_conflict_domains' => true,
        'no_deprecated_options' => true,
        'no_deprecated_redirect_resources' => true,
        'no_deprecated_scriptlets' => true,
        'no_dupe_domains' => true,
        'no_dupe_options' => true,
        'no_dupe_rules' => true,
        'no_invalid_abp_extended_css_selectors' => true,
        'no_invalid_id_selectors' => true,
        'no_invalid_if_directive_values' => true,
        'no_invalid_option_context' => true,
        'no_invalid_redirect_resources' => true,
        'no_invalid_scriptlets' => true,
        'no_short_rules' => false,
        'no_unsupported_option_negation' => true,
        'no_uppercase_domains' => true,
    ] {
        /** @param array<array-key, mixed> $value */
        set(array $value) {
            $this->rules = Helper::resolveOptions($this->rules, $value, 'rule');
        }
    }

    /**
     * @param array{
     *   paths?: list<string>,
     *   excludes?: list<string>,
     *   rules?: _LinterRules,
     *   ignoreErrors?: list<_ConfigIgnoredError>
     * } $config User-defined configuration from the config file
     * @param array{path: string|null} $cmdOpt Command options
     */
    public function make(array $config, array $cmdOpt): self
    {
        $this->paths = File::paths(
            $cmdOpt['path'] ?? $config['paths'] ?? [],
            $config['excludes'] ?? [],
        );

        $this->rules = $config['rules'] ?? [];
        $this->ignoreErrors = $this->normalizeIgnorePaths($config['ignoreErrors'] ?? []);

        return $this;
    }

    /**
     * @param list<_ConfigIgnoredError> $ignoreErrors
     * @return list<_ConfigIgnoredError>
     */
    private function normalizeIgnorePaths(array $ignoreErrors): array
    {
        foreach ($ignoreErrors as &$ignore) {
            if (is_string($ignore)) {
                continue;
            }

            if (isset($ignore['path'])) {
                $ignore['path'] = Path::normalize($ignore['path']);
            }

            if (isset($ignore['paths'])) {
                $ignore['paths'] = array_map(
                    fn($p) => Path::normalize($p),
                    (array) $ignore['paths'],
                );
            }
        }

        return $ignoreErrors;
    }
}
