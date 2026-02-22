<?php

namespace Realodix\Haiku\Fixer;

use Realodix\Haiku\App;
use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Console\CommandOptions;
use Realodix\Haiku\Console\OutputLogger;
use Symfony\Component\Filesystem\Filesystem;

final class Fixer
{
    public function __construct(
        private Processor $processor,
        private Config $config,
        private Filesystem $fs,
        private Cache $cache,
        private OutputLogger $logger,
    ) {}

    /**
     * Entry point for file or directory processing.
     */
    public function handle(CommandOptions $cmdOpt): void
    {
        $config = $this->config->fixer($cmdOpt);
        $this->cache->prepareForRun(
            $config->paths,
            $this->config->getCachePath($cmdOpt->cachePath),
            $cmdOpt->ignoreCache,
        );

        foreach ($config->paths as $path) {
            $this->processFile($path, $config);
        }

        $this->cache->repository()->save();
    }

    /**
     * @param string $path Path to file
     * @param \Realodix\Haiku\Config\FixerConfig $config Fixer configuration
     */
    private function processFile(string $path, $config): void
    {
        $content = $this->read($path);

        if ($content === null || $this->shouldSkip($path, $content)) {
            return;
        }

        $this->logger->processing($path);

        if ($config->backup) {
            $this->backup($path);
        }

        $content = $this->processor->process($content);
        $content = implode("\n", $content)."\n";
        $this->fs->dumpFile($path, $content);

        $this->cache->set($path, $this->hash($content));
        $this->logger->processed($path);
    }

    /**
     * Read file content.
     *
     * @param string $filePath Path to file
     * @return list<string>|null
     */
    private function read(string $filePath): ?array
    {
        if (!is_readable($filePath)) {
            $this->logger->error("Cannot read: {$filePath}");

            return null;
        }

        $rawContent = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($rawContent === false) {
            $this->logger->error("Failed to read file: {$filePath}");

            return null;
        }

        return $rawContent;
    }

    /**
     * Determine whether a file should be skipped.
     *
     * @param string $path Path to file
     * @param array<int, string> $content File content
     */
    private function shouldSkip(string $path, array $content): bool
    {
        // Empty file
        if (trim(implode($content)) === '') {
            $this->logger->skipped($path);

            return true;
        }

        $fingerprint = $this->hash(implode("\n", $content)."\n");
        if ($this->cache->isValid($path, $fingerprint)) {
            $this->logger->skipped($path);

            return true;
        }

        return false;
    }

    /**
     * Create a backup of the file at the given path.
     *
     * @param string $filePath Path to file
     */
    private function backup(string $filePath): void
    {
        $timestamp = date('Ymd-His');
        $backupPath = $filePath."_{$timestamp}.bak";

        try {
            $this->fs->copy($filePath, $backupPath);
        } catch (\RuntimeException $e) {
            $this->logger->error("Failed to create backup for: {$filePath}");
        }
    }

    /**
     * Generate a deterministic content fingerprint.
     *
     * @param string $data The data to hash.
     * @return string The computed hash value.
     */
    private function hash(string $data): string
    {
        $config = app(\Realodix\Haiku\Config\FixerConfig::class);
        $flags = collect($config->getFlag())
            ->reject(static fn($value) => $value === false || $value === null)
            ->sortKeys()->toJson();

        if (str_contains(App::VERSION, '.x')) {
            $v = App::version();
        } else {
            // get major and minor version
            $v = explode('.', App::version());
            $v = implode('.', array_slice($v, 0, 2));
        }

        return hash('xxh128', $data.$v.$flags);
    }

    /**
     * @return \Realodix\Haiku\Console\Statistics
     */
    public function stats()
    {
        return $this->logger->stats();
    }
}
