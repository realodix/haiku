<?php

namespace Realodix\Haiku\Config;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

final class FixerConfig
{
    /** @var array<string> */
    public array $paths;

    /** @var array<string, bool> */
    public array $options = [
        'backup' => false,
        'keep_empty_lines' => false,
        'xmode' => false,
    ];

    /**
     * @param array<string, array<string>|array<string, bool>> $config User-defined configuration from the config file
     * @param array{paths?: array<string>} $cmdOpt Command options
     */
    public function make(array $config, array $cmdOpt): self
    {
        $this->paths = $this->paths(
            $cmdOpt['paths'] ?? $config['paths'] ?? [],
            $config['excludes'] ?? [],
        );

        foreach ($config['options'] ?? [] as $name => $value) {
            $this->options[$name] = $value;
        }

        return $this;
    }

    /**
     * @param array<string> $paths
     * @param array<string> $excludes Excludes files or dirs
     * @return array<string>
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

    /**
     * @param string $dir The directory to use for the search
     * @param array<string> $excludes Excludes files or dirs
     * @return \Symfony\Component\Finder\Finder
     */
    public function finder(string $dir, array $excludes)
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
