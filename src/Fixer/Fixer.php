<?php

namespace Realodix\Haiku\Fixer;

use Realodix\Haiku\App;
use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Console\OutputLogger;
use Realodix\Haiku\Enums\Scope;
use Realodix\Haiku\Fixer\ValueObject\FixerRunContext;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

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
    public function handle(FixerRunContext $ctx): void
    {
        $config = $this->config->load(Scope::F, $ctx->configFile);
        $fixerConfig = $config->fixer($ctx->path ? ['paths' => [$ctx->path]] : []);

        $this->cache->prepareForRun(
            $fixerConfig->paths,
            $ctx->cachePath ?? $config->cacheDir,
            $ctx->ignoreCache,
        );

        foreach ($fixerConfig->paths as $path) {
            $path = Path::canonicalize($path);
            $content = $this->read($path);
            if ($content === null) {
                continue;
            }

            $contentHash = $this->hash(implode("\n", $content)."\n");
            if (
                $this->cache->isValid($path, $contentHash)
                || trim(implode($content)) === '' // empty file
            ) {
                $this->logger->skipped($path);

                continue;
            }

            $this->logger->processing($path);
            $this->write($path, $this->processor->process($content, $ctx->keepEmptyLines, $ctx->xMode));
            $this->logger->processed($path);
        }

        $this->cache->repository()->save();
    }

    /**
     * Read file content.
     *
     * @param string $filePath Path to file
     * @return array<string>|null
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
     * Write processed content to a file.
     *
     * @param string $filePath Path to file
     * @param array<string> $content Processed content
     *
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     */
    private function write(string $filePath, array $content): void
    {
        $content = implode("\n", $content)."\n";
        $this->fs->dumpFile($filePath, $content);

        $this->cache->set($filePath, $this->hash($content));
    }

    private function hash(string $data): string
    {
        if (str_contains(App::VERSION, '.x')) {
            $v = App::version();
        } else {
            // get major and minor version
            $v = explode('.', App::version());
            $v = implode('.', array_slice($v, 0, 2));
        }

        return hash('xxh128', $data.$v);
    }

    /**
     * @return \Realodix\Haiku\Console\Statistics
     */
    public function stats()
    {
        return $this->logger->stats();
    }
}
