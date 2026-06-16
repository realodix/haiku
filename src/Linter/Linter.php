<?php

namespace Realodix\Haiku\Linter;

use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Enums\Section;
use Realodix\Haiku\Linter\Rules\Rule;

/**
 * @phpstan-import-type _RuleError from RuleErrorBuilder
 */
final class Linter
{
    /** @var list<Rule> */
    private array $rules;

    private ErrorReporter $errorReporter;

    public function __construct(
        private Config $config,
        private Cache $cache,
    ) {
        $this->errorReporter = new ErrorReporter;
        $this->rules = Util::loadLinterRules();
    }

    /**
     * Run the linter against the configured paths.
     *
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt CLI runtime options
     * @param (callable(int): void)|null $onStart Callback executed before starting analysis,
     *                                            receives the total number of files
     * @param (callable(): void)|null $onAdvance Callback executed after each file is analysed
     */
    public function run($cmdOpt, ?callable $onStart = null, ?callable $onAdvance = null): ErrorReporter
    {
        $config = $this->config->linter($cmdOpt);
        $ignoredErrors = IgnoredErrors::load($config, $cmdOpt);

        $this->cache->prepareForRun($config->paths, $cmdOpt, Section::L);

        if ($onStart !== null) {
            $onStart(count($config->paths));
        }

        foreach ($config->paths as $path) {
            $this->analyseFile($path, $config, $ignoredErrors);

            if ($onAdvance !== null) {
                $onAdvance();
            }
        }

        $this->cache->repository()->save();

        $ignoredErrors->reportUnmatched($this->errorReporter);

        return $this->errorReporter;
    }

    /**
     * Analyse a single file and report errors.
     *
     * @param string $path The path to the file to be analysed
     * @param \Realodix\Haiku\Config\LinterConfig $config
     * @param IgnoredErrors $ignoredErrors The collection of errors to ignore
     */
    private function analyseFile(string $path, $config, IgnoredErrors $ignoredErrors): void
    {
        $content = $this->read($path);
        if ($content === null) {
            $this->errorReporter->addGlobalError(sprintf('Cannot read: %s', $path));

            return;
        }

        $fingerprint = hash('xxh128', implode("\n", $content).$config->fingerprintSeed());

        // Cache hit: restore cached errors and apply ignore filters
        if ($this->cache->isValid($path, $fingerprint)) {
            $cached = $this->cache->get($path);
            /** @var list<_RuleError> */
            $cachedErrors = $cached['errors'] ?? [];
            foreach ($cachedErrors as $error) {
                if ($ignoredErrors->shouldIgnoreExact($path, $error['message'])) {
                    continue;
                }

                if ($ignoredErrors->shouldIgnore($path, $error['message'])) {
                    continue;
                }

                $this->errorReporter->add($path, $error);
            }

            return;
        }

        // Cache miss: run analysis
        $rawErrors = [];
        foreach ($this->rules as $rule) {
            foreach ($rule->check($content, new RuleErrorBuilder) as $error) {
                $rawErrors[] = $error;

                if ($ignoredErrors->shouldIgnore($path, $error['message'])) {
                    continue;
                }

                $this->errorReporter->add($path, $error);
            }
        }

        $this->cache->set($path, $fingerprint, [
            'errors' => $rawErrors,
            'timestamp' => time(),
        ]);
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
}
