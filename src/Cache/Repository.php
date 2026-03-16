<?php

namespace Realodix\Haiku\Cache;

use Realodix\Haiku\Enums\Section;
use Symfony\Component\Filesystem\Filesystem;

final class Repository
{
    const DEFAULT_FILENAME = '.haiku_cache.json';

    /**
     * The array of stored values.
     *
     * @var array<string, mixed>
     */
    private array $storage = [];

    private string $cachePath = self::DEFAULT_FILENAME;

    private string $section = Section::F->value;

    public function __construct(
        private Filesystem $fs,
    ) {}

    /**
     * Sets the path of the cache file to use.
     *
     * @param string $cachePath The cache file path.
     */
    public function setCacheFile(string $cachePath): self
    {
        $this->cachePath = $cachePath;

        return $this;
    }

    /**
     * Sets the active section within the cache file.
     *
     * @param \Realodix\Haiku\Enums\Section $section The section to set
     */
    public function setSection(Section $section): self
    {
        $this->section = $section->value;

        return $this;
    }

    /**
     * Load the cache from a file.
     */
    public function load(): self
    {
        if (file_exists($this->cachePath)) {
            $content = @file_get_contents($this->cachePath);
            if ($content === false) {
                throw new \RuntimeException("Failed to read cache file: {$this->cachePath}");
            }

            try {
                /** @var array<string, mixed>|null $data */
                $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \UnexpectedValueException(
                    "Failed to decode cache file: {$this->cachePath}. Error: {$e->getMessage()}",
                );
            }

            $this->storage = $data ?? [];
        }

        return $this;
    }

    /**
     * Persists the in-memory storage to disk.
     */
    public function save(): void
    {
        try {
            $json = json_encode($this->storage, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \UnexpectedValueException(
                'Failed to encode cache data to JSON. Error: '.$e->getMessage(),
            );
        }

        $this->fs->dumpFile($this->cachePath, $json);
    }

    /**
     * Stores data for the given key in the active section.
     *
     * @param string $key The key to set
     * @param array<string, mixed> $data The data to set
     */
    public function set(string $key, array $data): void
    {
        $this->storage[$this->section][$key] = $data;
    }

    /**
     * Retrieves stored data for the given key in the active section.
     *
     * @param string $key The key to retrieve
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        return $this->storage[$this->section][$key] ?? null;
    }

    /**
     * Returns all entries for the active section.
     *
     * @return array<string, mixed>|array{}
     */
    public function all(): array
    {
        return $this->storage[$this->section] ?? [];
    }

    /**
     * Removes a key from the active section.
     *
     * @param string $key The key to remove
     */
    public function remove(string $key): void
    {
        unset($this->storage[$this->section][$key]);
    }

    /**
     * Clears all entries in the active section.
     */
    public function clear(): void
    {
        $this->storage[$this->section] = [];
    }
}
