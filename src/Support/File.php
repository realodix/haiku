<?php

namespace Realodix\Haiku\Support;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

final class File
{
    /**
     * Read file content.
     *
     * @param string $filePath Path to file
     * @return list<string>|null
     */
    public static function read(string $filePath): ?array
    {
        if (!is_readable($filePath)) {
            return null;
        }

        $content = file($filePath, FILE_IGNORE_NEW_LINES);

        return $content === false ? null : $content;
    }

    /**
     * Dumps content to a file with an incremental retry mechanism.
     *
     * This is particularly useful in environments like Windows, where transient
     * file locks can cause temporary access denied errors.
     *
     * @param string $path The target file path
     * @param string $content The content to write
     */
    public static function safeDumpFile(string $path, string $content): void
    {
        $fs = new Filesystem;
        $maxRetries = 10;
        $baseRetryDelay = 50000; // 50ms in microseconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $fs->dumpFile($path, $content);

                return;
            } catch (\RuntimeException $e) {
                // If this was the final attempt, re-throw the exception
                if ($attempt === $maxRetries) {
                    throw $e;
                }

                // Incremental delay: 50ms, 100ms, 150ms, etc.
                usleep($attempt * $baseRetryDelay);
            }
        }
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
