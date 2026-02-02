<?php

namespace Realodix\Haiku\Config;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

final class FixerConfig
{
    /** @var array<string> */
    public array $paths;

    /**
     * @param array<string, array<string>> $config User-defined configuration from the config file
     * @param array{paths?: array<string>} $custom Custom configuration from the CLI
     */
    public function make(array $config, array $custom): self
    {
        $this->paths = $this->paths(
            $custom['paths'] ?? $config['paths'] ?? [],
            $config['excludes'] ?? [],
        );

        return $this;
    }

    /**
     * @param array<string> $paths
     * @param array<string> $excludes Excludes files or dirs
     * @return array<string>
     */
    private function paths(array $paths, array $excludes): array
    {
        $rootPath = project_path();
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
