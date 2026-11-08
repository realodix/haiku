<?php

namespace Realodix\Haiku\Config;

use Realodix\Haiku\Support\File;

/**
 * @phpstan-type _FmtFlags array{
 *  all?: bool,
 *  adg_non_basic_rule_modifier: bool,
 *  attr_to_basic_selector: 'strict'|'loose'|false,
 *  convert_abp_ext_selectors: bool,
 *  convert_legacy_remove_action: bool,
 *  fix_domain_separators: bool,
 *  merge_domains: bool,
 *  merge_options: bool,
 *  migrate_deprecated_options: bool,
 *  option_syntax: 'native'|'long'|'short'|false,
 *  remove_empty_lines: bool|'keep_before_comment',
 *  remove_subdomains: bool,
 *  remove_wildcard_covered_domains: bool,
 *  sort_domains: bool,
 *  sort_options: 'name'|'type'|false,
 *  sort_rules: false|array{cosmetic_by_selector: bool},
 * }
 */
final class FormatterConfig
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

    /** @var _FmtFlags */
    public array $flags = [
        'adg_non_basic_rule_modifier' => false,
        'attr_to_basic_selector' => false,
        'convert_abp_ext_selectors' => false,
        'convert_legacy_remove_action' => false,
        'fix_domain_separators' => false,
        'merge_domains' => true,
        'merge_options' => true,
        'migrate_deprecated_options' => false,
        'option_syntax' => false,
        'remove_empty_lines' => 'keep_before_comment',
        'remove_subdomains' => false,
        'remove_wildcard_covered_domains' => false,
        'sort_domains' => true,
        'sort_options' => 'type',
        'sort_rules' => ['cosmetic_by_selector' => true],
    ] {
        /** @param array<string, mixed> $userCfg */
        set(array $userCfg) {
            $enabledValues = [
                'attr_to_basic_selector' => 'strict',
                'option_syntax' => 'native',
            ];
            $this->flags = Helper::resolveOptions($this->flags, $userCfg, $enabledValues);
        }
    }

    /**
     * Initializes the configuration by merging file-based config and command-line options.
     *
     * @param array{
     *   paths?: list<string>,
     *   excludes?: list<string>,
     *   backup?: bool,
     *   flags?: _FmtFlags
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
