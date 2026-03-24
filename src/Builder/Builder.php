<?php

namespace Realodix\Haiku\Builder;

use Illuminate\Support\Arr;
use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Console\OutputLogger;
use Realodix\Haiku\Enums\Section;
use Realodix\Haiku\Helper;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @phpstan-import-type _FilterSet from \Realodix\Haiku\Config\BuilderConfig
 */
final class Builder
{
    public function __construct(
        private Config $config,
        private Filesystem $fs,
        private Cache $cache,
        private OutputLogger $logger,
    ) {}

    /**
     * Main entry point for building filter lists.
     *
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt CLI runtime options
     */
    public function handle($cmdOpt): void
    {
        $filterSets = $this->config->builder($cmdOpt)->filterSet;
        $this->cache->prepareForRun(
            // builder.filter_list.filename
            array_map(fn($filterSet) => $filterSet['output_path'], $filterSets),
            $cmdOpt,
            Section::B,
        );

        foreach ($filterSets as $filterSet) {
            $this->buildFilterList($filterSet, $cmdOpt);
        }

        $this->cache->repository()->save();
    }

    /**
     * Builds a single filter list.
     *
     * @param _FilterSet $filterSet
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt CLI runtime options
     */
    private function buildFilterList(array $filterSet, $cmdOpt): void
    {
        // Step 1: Read all source files or URLs
        $outputPath = $filterSet['output_path'];
        $header = $filterSet['header'];
        $rawContent = $this->read($filterSet['source']);

        if ($rawContent === null) {
            $this->logger->skipped($outputPath);

            return;
        }

        // Step 2: Preparing content
        $content = Cleaner::clean($rawContent, $filterSet['remove_duplicates']);
        $fingerprint = $this->sourceHash($content, [$header]);

        if (!$cmdOpt->ignoreCache && $this->cache->isValid($outputPath, $fingerprint)) {
            $this->logger->skipped($outputPath);

            return;
        }

        // Step 3: Build and write
        $finalContent = array_merge([$this->header($header)], $content);
        $this->fs->dumpFile($outputPath, ltrim(Helper::joinLines($finalContent)));
        $this->cache->set($outputPath, $fingerprint);
        $this->logger->processed($outputPath);
    }

    /**
     * Generates the header string.
     */
    private function header(string $data): string
    {
        $date = new \DateTime()->format('D, d M Y H:i:s \G\M\T');

        $data = str_replace('%timestamp%', $date, $data);
        $data = rtrim($data);

        return $data;
    }

    /**
     * Reads all source files or URLs.
     *
     * @param array<int, string> $paths
     * @return array<int, string>|null Source contents, or null if a read fails.
     */
    private function read($paths): ?array
    {
        $text = [];

        foreach ($paths as $path) {
            $data = null;

            if (filter_var($path, FILTER_VALIDATE_URL)) {
                $context = stream_context_create(['http' => ['timeout' => 5]]);
                $data = @file($path, 0, $context) ?: null;
            } elseif (is_readable($path)) {
                $data = file($path);
            }

            if ($data === null) {
                $this->logger->error("Failed to read: {$path}");

                return null;
            }

            $text[] = $data;
        }

        return Arr::flatten($text);
    }

    /**
     * Computes a deterministic hash for the given source contents.
     *
     * @param array<int, string> $sources Source contents.
     * @return string A hash that uniquely represents the current source state.
     */
    private function sourceHash(array ...$sources): string
    {
        return hash('xxh128', implode('', array_merge(...$sources)));
    }

    /**
     * @return \Realodix\Haiku\Console\Statistics
     */
    public function stats()
    {
        return $this->logger->stats();
    }
}
