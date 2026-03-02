<?php

namespace Realodix\Haiku\Fixer;

use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Fixer\ValueObject\Result;
use Realodix\Haiku\Helper;
use Symfony\Component\Filesystem\Filesystem;

final class FileFixer
{
    public function __construct(
        private Processor $processor,
        private Filesystem $fs,
        private Cache $cache,
    ) {}

    /**
     * @param \Realodix\Haiku\Config\FixerConfig $config
     */
    public function fix(string $path, $config, string $hashPrefix): Result
    {
        $content = $this->read($path);

        if ($content === null) {
            return new Result($path, 'error');
        }

        if ($this->shouldSkip($path, $content, $hashPrefix)) {
            return new Result($path, 'skipped');
        }

        if ($config->backup) {
            $this->backup($path);
        }

        $content = $this->processor->process($content);
        $content = Helper::joinLines($content);

        $this->fs->dumpFile($path, $content);

        return new Result($path, 'processed', $this->hash($content, $hashPrefix));
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

    private function backup(string $filePath): void
    {
        $timestamp = date('Ymd-His');
        $this->fs->copy($filePath, "{$filePath}_{$timestamp}.bak", true);
    }

    /**
     * Determine whether a file should be skipped.
     *
     * @param string $path Path to file
     * @param array<int, string> $content File content
     */
    private function shouldSkip(string $path, array $content, string $hashPrefix): bool
    {
        if (trim(implode($content)) === '') {
            return true;
        }

        $fingerprint = $this->hash(Helper::joinLines($content), $hashPrefix);

        return $this->cache->isValid($path, $fingerprint);
    }

    private function hash(string $data, string $hashPrefix): string
    {
        return hash('xxh128', $data.$hashPrefix);
    }
}
