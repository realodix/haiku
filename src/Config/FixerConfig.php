<?php

namespace Realodix\Haiku\Config;

use Realodix\Haiku\App;
use Symfony\Component\Filesystem\Path;

/**
 * @phpstan-type _FixerFlags array{
 *  adg_non_basic_rule_modifier: bool,
 *  attr_to_basic_selector: null|'strict'|'loose',
 *  combine_option_sets: bool,
 *  domain_order: null|'name'|'normal'|'negated_first'|'localhost_first'|'localhost_negated_first',
 *  migrate_deprecated_options: bool,
 *  no_legacy_ext_selectors: bool,
 *  no_legacy_remove_action: bool,
 *  normalize_domain: bool,
 *  normalize_domain_separators: bool,
 *  option_format: null|'native'|'long'|'short',
 *  option_order: false|'name'|'type',
 *  reduce_subdomains: bool,
 *  reduce_wildcard_covered_domains: bool,
 *  remove_empty_lines: bool|'keep_before_comment',
 *  remove_unnecessary_wildcard: bool,
 * }
 */
final class FixerConfig
{
    /**
     * List of resolved absolute file paths to be processed
     *
     * @var array<int, string>
     */
    public private(set) array $paths;

    /**
     * Whether to create a backup before modifying files
     */
    public private(set) bool $backup;

    /** @var _FixerFlags */
    public array $flags = [
        'adg_non_basic_rule_modifier' => false,
        'attr_to_basic_selector' => null,
        'combine_option_sets' => false,
        'domain_order' => 'negated_first',
        'migrate_deprecated_options' => false,
        'no_legacy_ext_selectors' => false,
        'no_legacy_remove_action' => false,
        'normalize_domain' => false,
        'normalize_domain_separators' => false,
        'option_format' => null,
        'option_order' => 'type',
        'reduce_subdomains' => false,
        'reduce_wildcard_covered_domains' => false,
        'remove_empty_lines' => 'keep_before_comment',
        'remove_unnecessary_wildcard' => false,
    ] {
        /** @param array<string, mixed> $value */
        set(array $value) {
            $this->flags = $this->resolveFlags($value);
        }
    }

    /**
     * Initializes the configuration by merging file-based config and command-line options.
     *
     * @param array{
     *   paths?: list<string>,
     *   excludes?: list<string>,
     *   backup?: bool,
     *   flags?: _FixerFlags
     * } $config User-defined configuration from the config file
     * @param array{path: string|null} $cmdOpt Command options
     */
    public function make(array $config, array $cmdOpt): self
    {
        $this->paths = Util::paths(
            $cmdOpt['path'] ?? $config['paths'] ?? [],
            $config['excludes'] ?? [],
        );

        $this->backup = $config['backup'] ?? false;
        $this->flags = $config['flags'] ?? [];

        return $this;
    }

    /**
     * Generates a fingerprint seed based on the current configuration.
     */
    public function fingerprintSeed(): string
    {
        $flags = collect($this->flags)
            ->reject(static fn($value) => $value === false || $value === null)
            ->sortKeys()->toJson();

        if (str_contains(App::VERSION, '.x')) {
            $v = App::version();
        } else {
            // get major and minor version
            $v = explode('.', App::version());
            $v = implode('.', array_slice($v, 0, 2));
        }

        return $v.$flags;
    }

    /**
     * Resolves and validates flag overrides.
     *
     * @param array<string, mixed> $override
     * @return _FixerFlags
     */
    private function resolveFlags(array $override): array
    {
        $override = $this->deprecatedFlags($override);

        return Util::resolveOverrides($this->flags, $override);
    }

    /**
     * Converts deprecated flags to their new names.
     *
     * @param array<string, bool|string> $override
     * @return array<string, bool|string>
     */
    private function deprecatedFlags(array $override): array
    {
        $renames = [
            // since v1.11.0
            'xmode' => 'fmode',
            // since v1.11.3
            'adg_non_basic_rules_modifiers' => 'adg_non_basic_rule_modifier',
            // since v1.12.0
            'normalize_domains' => 'normalize_domain',
        ];

        foreach ($renames as $old => $new) {
            if (array_key_exists($old, $override)) {
                $override[$new] = $override[$old];
                unset($override[$old]);
            }
        }

        return $override;
    }
}
