<?php

namespace Realodix\Haiku\Test\Linter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Console\CommandOptions;
use Realodix\Haiku\Linter\Linter;
use Realodix\Haiku\Test\TestCase;

class LinterCacheTest extends TestCase
{
    private string $filterFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filterFile = $this->tmpDir.'/filter.txt';
    }

    private function makeLinter(): Linter
    {
        return new Linter(app(Config::class), app(Cache::class));
    }

    private function makeConfig(string $filePath, string $cachePath): CommandOptions
    {
        $configFile = $this->tmpDir.'/haiku_cache.yml';
        $this->fs->dumpFile($configFile, <<<YAML
linter:
  paths:
    - {$filePath}
  rules:
    no_extra_blank_lines: false
YAML);

        return new CommandOptions(
            cachePath: $cachePath,
            configFile: $configFile,
        );
    }

    /**
     * First run: errors are generated and stored in cache.
     * Second run: errors are retrieved from cache without re-analyzing.
     */
    #[PHPUnit\Test]
    public function cacheStoresAndRestoresErrors(): void
    {
        // A file that triggers a domain_case error: uppercase domain
        $this->fs->dumpFile($this->filterFile, '||Example.com^');

        $linter = $this->makeLinter();
        $cmdOpt = $this->makeConfig($this->filterFile, $this->cacheFile);

        // First run: should analyse and cache errors
        $reporter1 = $linter->run($cmdOpt);
        $errors1 = $reporter1->getErrors();
        $this->assertNotEmpty($errors1, 'First run should find errors');

        // Second run: same file unchanged — cache should be hit
        $linter2 = $this->makeLinter();
        $reporter2 = $linter2->run($cmdOpt);
        $errors2 = $reporter2->getErrors();

        $this->assertSame(
            $errors1[$this->filterFile] ?? [],
            $errors2[$this->filterFile] ?? [],
            'Cached errors should match original errors',
        );
    }

    /**
     * When the file content changes, the cache is invalidated and re-analysis runs.
     */
    #[PHPUnit\Test]
    public function cacheIsInvalidatedOnFileChange(): void
    {
        // First run: file with an error
        $this->fs->dumpFile($this->filterFile, '||Example.com^');
        $linter = $this->makeLinter();
        $cmdOpt = $this->makeConfig($this->filterFile, $this->cacheFile);
        $reporter1 = $linter->run($cmdOpt);
        $this->assertNotEmpty($reporter1->getErrors());

        // Modify the file to a clean one
        $this->fs->dumpFile($this->filterFile, '||example.com^');

        // Second run: cache should be invalidated, no errors expected
        $linter2 = $this->makeLinter();
        $reporter2 = $linter2->run($cmdOpt);
        $this->assertEmpty($reporter2->getErrors(), 'Errors should be empty after fixing the file');
    }

    /**
     * When --force (ignoreCache) is set, the cache is bypassed and re-analysis runs.
     */
    #[PHPUnit\Test]
    public function forceFlagBypassesCache(): void
    {
        // First run to populate the cache
        $this->fs->dumpFile($this->filterFile, '||Example.com^');
        $linter = $this->makeLinter();
        $cmdOpt = $this->makeConfig($this->filterFile, $this->cacheFile);
        $linter->run($cmdOpt);

        // Now fix the file but use ignoreCache=false (normal) — should return cached errors
        $this->fs->dumpFile($this->filterFile, '||example.com^');

        // Force run: ignores cache, re-analyses the (now clean) file
        $configFile = $this->tmpDir.'/haiku_cache.yml';
        $forceCmdOpt = new CommandOptions(
            cachePath: $this->cacheFile,
            configFile: $configFile,
            ignoreCache: true,
        );
        $linter3 = $this->makeLinter();
        $reporter3 = $linter3->run($forceCmdOpt);
        $this->assertEmpty($reporter3->getErrors(), 'Force flag should re-analyse and find no errors');
    }

    /**
     * A clean file (no errors) should be cached with an empty errors array.
     * A subsequent run should still return no errors.
     */
    #[PHPUnit\Test]
    public function cleanFileIsCachedWithNoErrors(): void
    {
        $this->fs->dumpFile($this->filterFile, '||example.com^');

        $linter = $this->makeLinter();
        $cmdOpt = $this->makeConfig($this->filterFile, $this->cacheFile);

        $reporter1 = $linter->run($cmdOpt);
        $this->assertEmpty($reporter1->getErrors());

        // Second run: should hit cache with empty errors
        $linter2 = $this->makeLinter();
        $reporter2 = $linter2->run($cmdOpt);
        $this->assertEmpty($reporter2->getErrors());
    }
}
