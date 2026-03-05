<?php

namespace Realodix\Haiku\Fixer;

use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Console\OutputLogger;
use Realodix\Haiku\Helper;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @phpstan-type _FixResult array{
 *  status: string,
 *  path: string,
 *  hash?: string,
 *  message?: string,
 * }
 */
final class Fixer
{
    /** @var _FixResult[] */
    public array $results;

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
        $this->cache->prepareForRun($config->paths, $cmdOpt);

        $results = [];
        if ($this->shouldRunParallel($config, $cmdOpt)) {
            $results = $this->parallel->run($this, $config, $cmdOpt);
        } else {
            foreach ($config->paths as $path) {
                $result = $this->fixFile($path, $config);

                $this->record($result);
                $results[] = $result;
            }
        }

        $this->cache->repository()->save();

        $this->results = $results;
    }

    /**
     * @param \Realodix\Haiku\Config\FixerConfig $config
     * @return _FixResult
     */
    public function fixFile(string $path, $config): array
    {
        $content = $this->read($path);

        if ($content === null) {
            return ['status' => 'error', 'path' => $path];
        }

        if ($this->shouldSkip($path, $content, $config)) {
            return ['status' => 'skipped', 'path' => $path];
        }

        if ($config->backup) {
            $this->backup($path);
        }

        $content = $this->processor->process($content);
        $content = Helper::joinLines($content);

        $this->safeDumpFile($path, $content);

        return [
            'status' => 'processed',
            'path' => $path,
            'hash' => $this->hash($content, $config),
        ];
    }

    /**
     * Record the result of a file processing.
     *
     * @param _FixResult $result
     */
    public function record($result): void
    {
        if ($result['status'] === 'processed') {
            $this->cache->set($result['path'], $result['hash']);
            $this->logger->processed($result['path']);
        }

        if ($result['status'] === 'skipped') {
            $this->logger->skipped($result['path']);
        }

        if ($result['status'] === 'error') {
            $message = $result['message'] ?? "Cannot read: {$result['path']}";
            $this->logger->error($message);
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
            return null;
        }

        $content = file($filePath, FILE_IGNORE_NEW_LINES);

        return $content === false ? null : $content;
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
            $this->fs->copy($filePath, $backupPath, true);
        } catch (\RuntimeException $e) {
            $this->logger->error("Failed to create backup for: {$filePath}");
        }
    }

    /**
     * Determine whether a file should be skipped.
     *
     * @param string $path Path to file
     * @param array<int, string> $content File content
     * @param \Realodix\Haiku\Config\FixerConfig $config
     */
    private function shouldSkip(string $path, array $content, $config): bool
    {
        if (trim(implode($content)) === '') {
            return true;
        }

        $fingerprint = $this->hash(Helper::joinLines($content), $config);

        return $this->cache->isValid($path, $fingerprint);
    }

    /**
     * Determine whether a file should be processed in parallel.
     *
     * @param \Realodix\Haiku\Config\FixerConfig $config Fixer configuration
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt CLI runtime options
     */
    private function shouldRunParallel($config, $cmdOpt): bool
    {
        if ($cmdOpt->forceParallel) {
            return true;
        }

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
     * Generate a deterministic content fingerprint.
     *
     * @param string $data The data to hash
     * @param \Realodix\Haiku\Config\FixerConfig $config
     * @return string The computed hash value
     */
    private function hash(string $data, $config): string
    {
        return hash('xxh128', $data.$config->fingerprintSeed());
    }

    /**
     * Dumps content to a file with an incremental retry mechanism.
     *
     * This is particularly useful in environments like Windows, where transient
     * file locks can cause temporary access denied errors.
     *
     * @param string $path The target file path
     * @param string $content The content to write
     *
     * @throws \Symfony\Component\Filesystem\Exception\IOException If all retry attempts fail
     */
    private function safeDumpFile(string $path, string $content): void
    {
        $maxRetries = 10;
        $baseRetryDelay = 50000; // 50ms in microseconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $this->fs->dumpFile($path, $content);

                return;
            } catch (\Symfony\Component\Filesystem\Exception\IOException $e) {
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
     * @return \Realodix\Haiku\Console\Statistics
     */
    public function stats()
    {
        return $this->logger->stats();
    }
}
