<?php

namespace Realodix\Haiku\Fixer;

use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Console\OutputLogger;
use Realodix\Haiku\Support\File;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @phpstan-type _FixResult array{
 *  status: string,
 *  path: string,
 *  hash?: string,
 *  message?: string,
 * }
 */
final class Runner
{
    /** @var _FixResult[] */
    public private(set) array $results;

    public function __construct(
        private Fixer $fixer,
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
    public function run($cmdOpt): void
    {
        $config = $this->config->fixer($cmdOpt);
        $this->cache->prepareForRun($config->paths, $cmdOpt);

        $results = [];
        if ($cmdOpt->parallel) {
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
        $content = File::read($path);

        if ($content === null) {
            return ['status' => 'error', 'path' => $path];
        }

        if ($this->shouldSkip($path, $content, $config)) {
            return ['status' => 'skipped', 'path' => $path];
        }

        if ($config->backup) {
            $this->backup($path);
        }

        $content = $this->fixer->fix($content);
        $content = Helper::joinLines($content);

        File::safeDumpFile($path, $content);

        return [
            'status' => 'processed',
            'path' => $path,
            'hash' => Helper::hash($content, $config),
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

        $fingerprint = Helper::hash(Helper::joinLines($content), $config);

        return $this->cache->isValid($path, $fingerprint);
    }
}
