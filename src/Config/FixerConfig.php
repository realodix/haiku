<?php

namespace Realodix\Haiku\Config;

use Realodix\Haiku\App;
use Realodix\Haiku\Helper;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

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
 *  option_format: null|'long'|'short',
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
        $this->paths = $this->paths(
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
        $flags = $this->flags;
        $override = $this->deprecatedFlags($override);

        // 'fmode' acts as a bulk toggle for all boolean flags
        if (array_key_exists('fmode', $override)) {
            $value = (bool) $override['fmode'];
            foreach ($flags as $name => $defaultValue) {
                is_bool($defaultValue) && $flags[$name] = $value;
            }
            unset($override['fmode']);
        }

        // Apply specific overrides
        foreach ($override as $name => $value) {
            if (!array_key_exists($name, $flags)) {
                $hint = Helper::getSuggestion(array_merge(array_keys($flags), ['fmode']), $name);
                throw new InvalidConfigurationException(sprintf(
                    'Unknown flag: "%s"'.($hint ? ", did you mean '%s'?" : '.'),
                    $name,
                    $hint,
                ));
            }

            $flags[$name] = $value;
        }

        return $flags;
    }

    /**
     * Resolves provided paths into a unique list of absolute file paths.
     *
     * @param array<int, string>|string $paths
     * @param array<int, string> $excludes Excludes files or dirs
     * @return array<int, string>
     */
    private function paths(array|string $paths, array $excludes): array
    {
        $rootPath = project_path();
        $paths = is_array($paths) ? $paths : [$paths];
        $paths = !empty($paths) ? $paths : [$rootPath];

        $resolvedPaths = [];
        foreach ($paths as $path) {
            if (Path::isRelative($path)) {
                $path = Path::makeAbsolute($path, $rootPath);
            }

            if (is_dir($path)) {
                $finder = $this->finder($path, $excludes);
                foreach ($finder as $file) {
                    $resolvedPaths[] = $file->getRealPath();
                }
            } else {
                $resolvedPaths[] = $path;
            }
        }

        $resolvedPaths = array_map(fn($path) => Path::canonicalize($path), $resolvedPaths);

        return array_unique($resolvedPaths);
    }

    /**
     * @param string $dir The directory to use for the search
     * @param array<int, string> $excludes Excludes files or dirs
     * @return \Symfony\Component\Finder\Finder
     */
    private function finder(string $dir, array $excludes)
    {
        if ($dir === project_path()) {
            $excludes = array_merge($excludes, ['vendor']);
        }

        $excludes = array_map(fn($paths) => Path::canonicalize($paths), $excludes);
        $excludes = array_unique($excludes);

        $finder = new Finder;
        $finder->files()
            ->in($dir)
            ->name(['*.txt', '*.adfl'])
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->notPath($excludes);

        return $finder;
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
