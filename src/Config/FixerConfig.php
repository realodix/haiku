<?php

namespace Realodix\Haiku\Config;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @phpstan-type _FixerFlags array{
 *  adg_non_basic_rules_modifiers: bool,
 *  combine_option_sets: bool,
 *  domain_order: null|'normal'|'negated_first'|'localhost_first'|'localhost_negated_first',
 *  migrate_deprecated_options: bool,
 *  normalize_domains: bool,
 *  option_format: null|'long'|'short',
 *  reduce_subdomains: bool,
 *  reduce_wildcard_covered_domains: bool,
 *  remove_empty_lines: bool|'keep_before_comment',
 * }
 */
final class FixerConfig
{
    /** @var array<int, string> */
    public array $paths;

    public bool $backup;

    /** @var _FixerFlags */
    private array $flags = [
        'adg_non_basic_rules_modifiers' => false,
        'combine_option_sets' => false,
        'domain_order' => 'negated_first',
        'migrate_deprecated_options' => false,
        'normalize_domains' => false,
        'option_format' => null,
        'reduce_subdomains' => false,
        'reduce_wildcard_covered_domains' => false,
        'remove_empty_lines' => true,
    ];

    /**
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
        $this->setFlag($config['flags'] ?? []);

        return $this;
    }

    /**
     * @param array<string, bool|string> $flags
     */
    public function setFlag(array $flags): self
    {
        $this->flags = $this->resolveFlags($flags);

        return $this;
    }

    /**
     * @param key-of<_FixerFlags>|null $name
     * @return ($name is string ? value-of<_FixerFlags> : _FixerFlags)
     */
    public function getFlag(?string $name = null)
    {
        return $name === null ? $this->flags : $this->flags[$name];
    }

    /**
     * @param array<string, bool|string> $override
     * @return array<string, bool|string>
     */
    private function resolveFlags(array $override = []): array
    {
        $flags = $this->flags;

        // @deprecated
        if (array_key_exists('xmode', $override)) {
            $override['fmode'] = $override['xmode'];
            unset($override['xmode']);
        }

        // Handle fmode if exists.
        if (array_key_exists('fmode', $override)) {
            $value = (bool) $override['fmode'];
            foreach ($flags as $name => $defaultValue) {
                is_bool($defaultValue) && $flags[$name] = $value;
            }
            unset($override['fmode']);
        }

        // Override with specific flags.
        foreach ($override as $name => $value) {
            if (!array_key_exists($name, $flags)) {
                throw new InvalidConfigurationException(sprintf('Unknown flag name: "%s".', $name));
            }

            $flags[$name] = $value;
        }

        return $flags;
    }

    /**
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
}
