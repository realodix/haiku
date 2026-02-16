<?php

namespace Realodix\Haiku\Config;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @phpstan-type FixerFlags array{
 *  migrate_deprecated_options: bool,
 *  normalize_domains: bool,
 *  reduce_subdomains: bool,
 *  reduce_wildcard_covered_domains: bool,
 *  remove_empty_lines: bool,
 *  xmode: bool
 * }
 */
final class FixerConfig
{
    /** @var array<int, string> */
    public array $paths;

    public bool $backup;

    /** @var FixerFlags */
    public array $flags = [
        'migrate_deprecated_options' => false,
        'normalize_domains' => false,
        'reduce_subdomains' => false,
        'reduce_wildcard_covered_domains' => false,
        'remove_empty_lines' => true,
        'xmode' => false,
    ];

    /**
     * @param array{
     *   paths: list<string>|null,
     *   excludes: list<string>|null,
     *   backup: bool|null,
     *   flags: array<string, bool>|null
     * } $config User-defined configuration from the config file
     * @param array{paths: array<int, string>|null} $cmdOpt Command options
     */
    public function make(array $config, array $cmdOpt): self
    {
        $this->paths = $this->paths(
            $cmdOpt['paths'] ?? $config['paths'] ?? [],
            $config['excludes'] ?? [],
        );

        $this->backup = $config['backup'] ?? false;

        foreach ($config['flags'] ?? [] as $name => $value) {
            $this->setFlag($name, $value);
        }

        return $this;
    }

    /**
     * @param array<int, string> $paths
     * @param array<int, string> $excludes Excludes files or dirs
     * @return array<int, string>
     */
    private function paths(array $paths, array $excludes): array
    {
        $rootPath = base_path();
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

    private function setFlag(string $name, bool $value): void
    {
        if (!array_key_exists($name, $this->flags)) {
            throw new InvalidConfigurationException(sprintf('Unknown flag name: "%s".', $name));
        }

        $this->flags[$name] = $value;
    }

    /**
     * @param string $dir The directory to use for the search
     * @param array<int, string> $excludes Excludes files or dirs
     * @return \Symfony\Component\Finder\Finder
     */
    private function finder(string $dir, array $excludes)
    {
        if ($dir === base_path()) {
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
