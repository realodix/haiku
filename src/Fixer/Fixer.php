<?php

namespace Realodix\Haiku\Fixer;

use Realodix\Haiku\App;
use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Console\OutputLogger;

final class Fixer
{
    public string $hashPrefix;

    public function __construct(
        private FileFixer $fileFixer,
        private ParallelRunner $parallel,
        private Config $config,
        private Cache $cache,
        private OutputLogger $logger,
    ) {}

    /**
     * Entry point for file or directory processing.
     *
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt CLI runtime options
     * @return \Realodix\Haiku\Fixer\ValueObject\Result[]
     */
    public function handle($cmdOpt): array
    {
        $config = $this->config->fixer($cmdOpt);
        $this->initializeHashPrefix($config);
        $this->cache->prepareForRun($config->paths, $cmdOpt);

        $results = [];

        if ($this->shouldRunParallel($config, $cmdOpt)) {
            $results = $this->parallel->run($this, $config, $cmdOpt);
        } else {
            foreach ($config->paths as $path) {
                $result = $this->fileFixer->fix($path, $config, $this->hashPrefix);

                $this->record($result);
                $results[] = $result;
            }
        }

        $this->cache->repository()->save();

        return $results;
    }

    /**
     * Record the result of a file processing.
     *
     * @param \Realodix\Haiku\Fixer\ValueObject\Result $result
     */
    public function record($result): void
    {
        if ($result->status === 'processed') {
            $this->cache->set($result->path, $result->hash);
            $this->logger->processed($result->path);
        }

        if ($result->status === 'skipped') {
            $this->logger->skipped($result->path);
        }

        if ($result->status === 'error') {
            $this->logger->error("Cannot read: {$result->path}");
        }
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
     * @param \Realodix\Haiku\Config\FixerConfig $config
     */
    private function initializeHashPrefix($config): void
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

        $this->hashPrefix = $v.$flags;
    }

    /**
     * @return \Realodix\Haiku\Console\Statistics
     */
    public function stats()
    {
        return $this->logger->stats();
    }
}
