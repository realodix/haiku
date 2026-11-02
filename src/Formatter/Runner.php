<?php

namespace Realodix\Haiku\Formatter;

use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Console\OutputLogger;
use Realodix\Haiku\Support\File;
use Realodix\Haiku\Support\Util;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @phpstan-type _FmtResultProcessed array{status: 'processed', path: string, hash: string}
 * @phpstan-type _FmtResultSkipped array{status: 'skipped', path: string}
 * @phpstan-type _FmtResultError array{status: 'error', path: string, message?: string}
 * @phpstan-type _FmtResult _FmtResultProcessed|_FmtResultSkipped|_FmtResultError
 */
final class Runner
{
    /** @var _FmtResult[] */
    public private(set) array $results;

    public function __construct(
        private Formatter $fmt,
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
        $config = $this->config->formatter($cmdOpt);
        $this->cache->prepareForRun($config->paths, $cmdOpt);

        $results = [];
        if ($cmdOpt->parallel) {
            $results = $this->parallel->run($this, $config, $cmdOpt);
        } else {
            foreach ($config->paths as $path) {
                $result = $this->formatFile($path, $config);

                $this->record($result);
                $results[] = $result;
            }
        }

        $this->cache->repository()->save();

        $this->results = $results;
    }

    /**
     * @param \Realodix\Haiku\Config\FormatterConfig $config
     * @return _FmtResult
     */
    public function formatFile(string $path, $config): array
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

        $content = $this->fmt->format($content);
        $content = Helper::joinLines($content);

        File::safeDumpFile($path, $content);

        return [
            'status' => 'processed',
            'path' => $path,
            'hash' => Util::hash($content, $config->flags),
        ];
    }

    /**
     * Record the result of a file processing.
     *
     * @param _FmtResult $result
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
        } catch (\RuntimeException) {
            $this->logger->error("Failed to create backup for: {$filePath}");
        }
    }

    /**
     * Determine whether a file should be skipped.
     *
     * @param string $path Path to file
     * @param array<int, string> $content File content
     * @param \Realodix\Haiku\Config\FormatterConfig $config
     */
    private function shouldSkip(string $path, array $content, $config): bool
    {
        if (trim(implode($content)) === '') {
            return true;
        }

        $fingerprint = Util::hash(Helper::joinLines($content), $config->flags);

        return $this->cache->isValid($path, $fingerprint);
    }
}
