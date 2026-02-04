<?php

namespace Realodix\Haiku\Builder;

use Illuminate\Support\Arr;
use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Console\CommandOptions;
use Realodix\Haiku\Console\OutputLogger;
use Realodix\Haiku\Enums\Section;
use Symfony\Component\Filesystem\Filesystem;

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
     */
    public function handle(CommandOptions $options): void
    {
        $config = $this->config->load(Section::B, $options->configFile);
        $filterSets = $config->builder()->filterSet;

        // Prepare cache repository for this run
        $this->cache->prepareForRun(
            // builder.filter_list.filename
            array_map(fn($filterSet) => $filterSet->outputPath, $filterSets),
            $config->cacheDir,
            $options->ignoreCache,
            Section::B,
        );

        foreach ($filterSets as $filterSet) {
            // Step 1: Read all source files or URLs
            $outputPath = $filterSet->outputPath;
            $header = $filterSet->header;
            $rawContent = $this->read($filterSet->source);

            if ($rawContent === null) {
                $this->logger->skipped($outputPath);

                continue;
            }

            // Step 2: Preparing content
            $content = Cleaner::clean($rawContent, $filterSet->unique);
            $sourceHash = $this->sourceHash($content, [$header]);

            if (!$options->ignoreCache && $this->cache->isValid($outputPath, $sourceHash)) {
                $this->logger->skipped($outputPath);

                continue;
            }

            // Step 3: Build and write
            $finalContent = array_merge([$this->header($header)], $content);
            $this->write($outputPath, $finalContent, $sourceHash);
            $this->logger->processed($outputPath);
        }

        // Save all updated cache entries to disk
        $this->cache->repository()->save();
    }

    /**
     * Filterlist header.
     */
    public function header(string $data): string
    {
        $date = new \DateTime()->format(\DateTimeInterface::RFC7231);

        $data = str_replace('%timestamp%', $date, $data);
        $data = rtrim($data);

        return $data;
    }

    /**
     * Reads all source files or URLs defined in the configuration.
     *
     * @param array<string> $paths
     * @return array<string>|null Source contents, or null if a read fails.
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
     * Writes the final output file from sources and metadata, and updates cache.
     *
     * @param string $outputPath The path to the output file
     * @param array<string> $content Source contents
     * @param string $sourceHash The hash representing the current source state
     *
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     */
    private function write(string $outputPath, array $content, string $sourceHash): void
    {
        $this->fs->dumpFile($outputPath, ltrim(implode("\n", $content))."\n");

        $this->cache->set($outputPath, $sourceHash);
    }

    /**
     * Generates a global hash representing all source contents combined.
     *
     * @param array<string> $sources Source contents.
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
