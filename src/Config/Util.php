<?php

namespace Realodix\Haiku\Config;

use Realodix\Haiku\Helper;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

final class Util
{
    /**
     * Resolves and validates configuration overrides.
     *
     * @param array<string, mixed> $baseConfig Current configuration array
     * @param array<string, mixed> $override Overrides to apply
     * @param string $type Type of configuration for error messages
     * @return array<string, mixed>
     */
    public static function resolveOverrides(array $baseConfig, array $override, string $type = 'flag'): array
    {
        // 'fmode' acts as a bulk toggle for all boolean values
        if (array_key_exists('fmode', $override)) {
            $value = (bool) $override['fmode'];
            foreach ($baseConfig as $name => $defaultValue) {
                if (is_bool($defaultValue)) {
                    $baseConfig[$name] = $value;
                }
            }
            unset($override['fmode']);
        }
        // Apply specific overrides
        foreach ($override as $name => $value) {
            if (!array_key_exists($name, $baseConfig)) {
                $hint = Helper::getSuggestion(array_merge(array_keys($baseConfig), ['fmode']), $name);
                throw new InvalidConfigurationException(sprintf(
                    'Unknown %s: "%s"'.($hint ? ", did you mean '%s'?" : '.'),
                    $type,
                    $name,
                    $hint,
                ));
            }
            $baseConfig[$name] = $value;
        }

        return $baseConfig;
    }

    /**
     * Resolves provided paths into a unique list of absolute file paths.
     *
     * @param array<int, string>|string $paths
     * @param array<int, string> $excludes Excludes files or dirs
     * @return array<int, string>
     */
    public static function paths(array|string $paths, array $excludes): array
    {
        $rootPath = base_path();
        $paths = is_array($paths) ? $paths : [$paths];
        $paths = !empty($paths) ? $paths : [$rootPath];

        $resolvedPaths = [];
        foreach ($paths as $path) {
            if (Path::isRelative($path)) {
                $path = Path::makeAbsolute($path, $rootPath);
            }

            if (is_dir($path)) {
                $finder = self::finder($path, $excludes);
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
     */
    private static function finder(string $dir, array $excludes): Finder
    {
        if ($dir === base_path()) {
            $excludes = array_merge($excludes, ['node_modules', 'vendor']);
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
