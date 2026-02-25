<?php

namespace Realodix\Haiku\Fixer;

use Realodix\Haiku\App;
use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Console\OutputLogger;
use Realodix\Haiku\Helper;
use Symfony\Component\Filesystem\Filesystem;

final class Fixer
{
    private string $hashPrefix;

    public function __construct(
        private Processor $processor,
        private ParallelRunner $parallel,
        private Config $config,
        private Filesystem $fs,
        private Cache $cache,
        private OutputLogger $logger,
    ) {}

    /**
     * Entry point for file or directory processing.
     *
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt CLI runtime options
     */
    public function handle($cmdOpt): void
    {
        $config = $this->config->fixer($cmdOpt);
        $this->initializeHashPrefix($config);
        $this->cache->prepareForRun($config->paths, $cmdOpt);

        if ($this->shouldRunParallel($config, $cmdOpt)) {
            $this->parallel->run($this, $config, $cmdOpt);
        } else {
            foreach ($config->paths as $path) {
                $this->record($this->processFile($path, $config));
            }
        }

        $this->cache->repository()->save();
    }

    /**
     * @param string $path Path to file
     * @param \Realodix\Haiku\Config\FixerConfig $config Fixer configuration
     * @return array{path: string, status: string, hash?: string}
     */
    public function processFile(string $path, $config): array
    {
        $content = $this->read($path);

        if ($content === null) {
            return ['status' => 'error', 'path' => $path];
        }

        if ($this->shouldSkip($path, $content)) {
            return ['status' => 'skipped', 'path' => $path];
        }

        $this->logger->processing($path);

        if ($config->backup) {
            $this->backup($path);
        }

        $content = $this->processor->process($content);
        $content = Helper::joinLines($content);
        $this->fs->dumpFile($path, $content);

        return [
            'status' => 'processed',
            'path' => $path,
            'hash' => $this->hash($content),
        ];
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
     * Determine whether a file should be processed in parallel.
     *
     * @param \Realodix\Haiku\Config\FixerConfig $config Fixer configuration
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt CLI runtime options
     */
    private function shouldRunParallel($config, $cmdOpt): bool
    {
        if (!$cmdOpt->parallel && !$cmdOpt->ignoreCache) {
            return false;
        }

        $minFiles = 4; // Minimum number of files
        $minAvgSize = 7 * 1024; // Minimum average file size in KB
        $paths = $config->paths;

        // Minimum file threshold
        $fileCount = count($paths);
        if ($fileCount < $minFiles) {
            return false;
        }

        // Calculate average file size (sample up to 20 files)
        $sampleSize = min(20, $fileCount);
        $totalSize = 0;
        for ($i = 0; $i < $sampleSize; $i++) {
            $size = @filesize($paths[$i]);
            if ($size !== false) {
                $totalSize += $size;
            }
        }

        $avgSize = $totalSize / $sampleSize;

        return $avgSize >= $minAvgSize;
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

        $fingerprint = $this->hash(Helper::joinLines($content));
        if ($this->cache->isValid($path, $fingerprint)) {
            $this->logger->skipped($path);

            return true;
        }

        return false;
    }

    /**
     * Generate a deterministic content fingerprint.
     *
     * @param string $data The data to hash.
     * @return string The computed hash value.
     */
    private function hash(string $data): string
    {
        return hash('xxh128', $data.$this->hashPrefix);
    }

    /**
     * @param \Realodix\Haiku\Config\FixerConfig $config
     */
    private function initializeHashPrefix($config): string
    {
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

        return $this->hashPrefix = $v.$flags;
    }

    /**
     * @return \Realodix\Haiku\Console\Statistics
     */
    public function stats()
    {
        return $this->logger->stats();
    }

    /**
     * Record the result of a file processing.
     *
     * @param array{path: string, status: string, hash?: string} $result
     */
    public function record(array $result): void
    {
        if ($result['status'] === 'processed') {
            $this->cache->set($result['path'], $result['hash']);
            $this->logger->processed($result['path']);
        }
    }
}
