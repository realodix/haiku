<?php

namespace Realodix\Haiku\Cache;

use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Enums\Section;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class Cache
{
    private bool $cacheCleared = false;

    public function __construct(
        private Repository $repository,
        private Filesystem $fs,
        private Config $config,
    ) {}

    public function repository(): Repository
    {
        return $this->repository;
    }

    /**
     * Initializes the cache state for the current run.
     *
     * - If $ignoreCache is false:
     *   Removes stale entries (keys not in $validKeys or pointing to non-existent files)
     * - If $ignoreCache is true:
     *   Clears the entire section cache (once per run)
     *
     * @param array<int, string> $validKeys $validKeys List of valid keys
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt CLI runtime options
     * @param Section $section Cache section to operate on
     * @param bool $pruning If true, stale entries are removed and cache can be cleared
     */
    public function prepareForRun(
        array $validKeys,
        $cmdOpt,
        Section $section = Section::F,
        bool $pruning = true,
    ): void {
        $resolvedPath = $this->resolvePath($cmdOpt->cachePath);

        $this->repository()
            ->setCacheFile($resolvedPath)
            ->setSection($section)
            ->load();

        if ($pruning && $cmdOpt->ignoreCache === false) {
            $this->cleanStaleEntries($validKeys);
        }

        if ($pruning && $cmdOpt->ignoreCache && !$this->cacheCleared) {
            $this->repository()->clear();
            $this->cacheCleared = true;
        }
    }

    /**
     * Set the cached data for the given key.
     *
     * @param string $key The key to set
     * @param string $value The reference value
     */
    public function set(string $key, string $value): void
    {
        $this->repository()->set($key, [
            'reference' => $value,
        ]);
    }

    /**
     * Checks whether the stored reference for a key matches the provided reference value.
     *
     * @param string $key The cache key
     * @param string $value The reference value
     */
    public function isValid(string $key, string $value): bool
    {
        $cacheEntry = $this->repository()->get($key);

        return data_get($cacheEntry, 'reference') === $value;
    }

    /**
     * Removes cache entries that are no longer valid.
     *
     * @param array<int, string> $validKeys A valid keys to keep
     */
    private function cleanStaleEntries(array $validKeys): void
    {
        $validKeys = array_flip($validKeys);
        $toRemove = [];

        foreach ($this->repository()->all() as $key => $entry) {
            if (!isset($validKeys[$key])) {
                $toRemove[] = $key;

                continue;
            }

            // Currently, the path is used as a key for the cache. When the first
            // validation passes, they must be checked for existence.
            if (!file_exists($key)) {
                $toRemove[] = $key;
            }
        }

        if (empty($toRemove)) {
            return;
        }

        foreach ($toRemove as $key) {
            $this->repository()->remove($key);
        }

        $this->repository()->save();
    }

    /**
     * Resolves the final cache file path and ensures required directories exist.
     *
     * @param string|null $path Custom cache directory path (can be relative, absolute, or null)
     * @return string The absolute path to the final cache file
     */
    private function resolvePath(?string $path): string
    {
        $path = $this->config->getCachePath($path);

        // 1. Default: no path provided -> use default cache file in baseDir
        if (empty($path)) {
            return Repository::DEFAULT_FILENAME;
        }

        $resolvedPath = Path::canonicalize($path);

        // 2. If exists, determine type
        if ($this->fs->exists($resolvedPath)) {
            return is_dir($resolvedPath)
                ? Path::join($resolvedPath, Repository::DEFAULT_FILENAME)
                : $resolvedPath;
        }

        // 3. Determine extension, but treat single-dot names like ".env" or ".tmp" as NO extension
        $basename = basename($resolvedPath);
        $rawExt = pathinfo($basename, PATHINFO_EXTENSION);
        // If basename starts with a dot AND contains no other dot, treat it as a dot-directory name (no extension).
        // Example: ".env" or ".tmp" -> consider NO extension.
        $isSingleDotName = str_starts_with($basename, '.') && strpos(substr($basename, 1), '.') === false;
        // hasExtension is true only when pathinfo reports an extension AND it's not a single-dot name
        $hasExtension = ($rawExt !== '') && !$isSingleDotName;

        // 4. Choose behavior
        if ($hasExtension) {
            $this->fs->mkdir(dirname($resolvedPath));

            return $resolvedPath;
        }

        // 5. Otherwise treat as directory (covers .tmp, .cache, .env and regular names)
        $this->fs->mkdir($resolvedPath);

        return Path::join($resolvedPath, Repository::DEFAULT_FILENAME);
    }
}
