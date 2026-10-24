<?php

namespace Realodix\Haiku\Config;

use Realodix\Haiku\Support\File;

/**
 * @phpstan-type _FixerFlags array{
 *  all?: bool,
 *  fmode?: bool,
 *  adg_non_basic_rule_modifier: bool,
 *  attr_to_basic_selector: 'strict'|'loose'|false,
 *  combine_option_sets: bool,
 *  convert_abp_ext_selectors: bool,
 *  convert_legacy_remove_action: bool,
 *  fix_domain_separators: bool,
 *  migrate_deprecated_options: bool,
 *  option_format: 'native'|'long'|'short'|false,
 *  option_order: 'name'|'type'|false,
 *  remove_empty_lines: bool|'keep_before_comment',
 *  remove_subdomains: bool,
 *  remove_wildcard_covered_domains: bool,
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
        'attr_to_basic_selector' => false,
        'combine_option_sets' => true,
        'convert_abp_ext_selectors' => false,
        'convert_legacy_remove_action' => false,
        'fix_domain_separators' => false,
        'migrate_deprecated_options' => false,
        'option_format' => false,
        'option_order' => 'type',
        'remove_empty_lines' => 'keep_before_comment',
        'remove_subdomains' => false,
        'remove_wildcard_covered_domains' => false,
    ] {
        /** @param array<array-key, mixed> $value */
        set(array $value) {
            $enabledValues = [
                'attr_to_basic_selector' => 'loose',
                'option_format' => 'native',
            ];
            $this->flags = Helper::resolveOptions($this->flags, $value, $enabledValues);
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
        $this->paths = File::paths(
            $cmdOpt['path'] ?? $config['paths'] ?? [],
            $config['excludes'] ?? [],
        );

        $this->backup = $config['backup'] ?? false;
        $this->flags = $config['flags'] ?? [];

        return $this;
    }
}
