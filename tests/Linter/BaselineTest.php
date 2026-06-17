<?php

namespace Realodix\Haiku\Test\Linter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Console\CommandOptions;
use Realodix\Haiku\Linter\ErrorReporter;
use Realodix\Haiku\Linter\IgnoredErrors;
use Realodix\Haiku\Linter\Linter;
use Realodix\Haiku\Test\TestCase;
use Symfony\Component\Filesystem\Path;

class BaselineTest extends TestCase
{
    #[PHPUnit\Test]
    public function mixedUserAndBaselineIgnore(): void
    {
        $ignoreErrors = ['user error'];
        $baselineErrors = [
            [
                'message' => 'baseline error',
                'path' => 'file.txt',
                'count' => 1,
            ],
        ];

        $ignoredErrors = new IgnoredErrors($ignoreErrors, $baselineErrors);

        $this->assertTrue($ignoredErrors->shouldIgnore('any.txt', 'user error'));
        $this->assertTrue($ignoredErrors->shouldIgnore('file.txt', 'baseline error'));
        $this->assertFalse($ignoredErrors->shouldIgnore('file.txt', 'baseline error')); // Count 1 exceeded
    }

    #[PHPUnit\Test]
    public function baselineIgnoreWithCount(): void
    {
        $baselineErrors = [
            [
                'message' => 'error message',
                'path' => 'path/to/file.txt',
                'count' => 2,
            ],
        ];

        $ignoredErrors = new IgnoredErrors([], $baselineErrors);

        // First two matches should be ignored
        $this->assertTrue($ignoredErrors->shouldIgnore('path/to/file.txt', 'error message'));
        $this->assertTrue($ignoredErrors->shouldIgnore('path/to/file.txt', 'error message'));

        // Third match should NOT be ignored
        $this->assertFalse($ignoredErrors->shouldIgnore('path/to/file.txt', 'error message'));
    }

    #[PHPUnit\Test]
    public function baselineNotReportedAsUnmatched(): void
    {
        $baselineErrors = [
            [
                'message' => 'unmatched baseline error',
                'path' => 'path/to/file.txt',
                'count' => 1,
            ],
        ];

        $ignoredErrors = new IgnoredErrors([], $baselineErrors);
        $reporter = new ErrorReporter;

        $ignoredErrors->reportUnmatched($reporter);

        $this->assertEmpty($reporter->getGlobalErrors());
    }

    #[PHPUnit\Test]
    public function baselineNormalization(): void
    {
        $ignoredErrors = new IgnoredErrors([], [['message' => 'foo']]);

        $reflection = new \ReflectionClass($ignoredErrors);
        $property = $reflection->getProperty('ignorePatterns');

        $this->assertSame(
            [['isBaseline' => true, 'message' => 'foo']],
            $property->getValue($ignoredErrors),
        );
    }

    /**
     * Ensure that when generating a baseline, files containing errors are still
     * cached. This prevents re-analysing unmodified files in subsequent runs.
     */
    #[PHPUnit\Test]
    public function cachesFilesWithErrorsDuringBaselineGeneration(): void
    {
        $configFile = $this->tmpDir.'/haiku_baseline_cache.yml';
        $dummyFile = $this->tmpDir.'/error_file.txt';

        $this->fs->dumpFile($configFile, <<<'YAML'
linter:
  paths:
    - tests/Integration/tmp/error_file.txt
YAML);

        // This triggers DomainCheck: "Unexpected empty domain.."
        $this->fs->dumpFile($dummyFile, 'example.com,##.ads');

        app(Linter::class)->run(new CommandOptions(
            configFile: 'tests/Integration/tmp/haiku_baseline_cache.yml',
            cachePath: $this->cacheFile,
            generateBaseline: true,
        ));

        // Now read cache to verify that $dummyFile is indeed cached
        $cache = app(Cache::class);
        $cachedData = $cache->get(Path::canonicalize($dummyFile));

        $this->assertNotNull($cachedData);
        $this->assertNotEmpty($cachedData['errors'] ?? []);
        $this->assertStringContainsString('Unexpected empty domain', $cachedData['errors'][0]['message'] ?? '');
    }

    #[PHPUnit\Test]
    public function baselineGenerationUsesCacheAndHandlesNewErrors(): void
    {
        $configFile = $this->tmpDir.'/haiku_baseline_test.yml';
        $dummyFile1 = $this->tmpDir.'/error_file1.txt';
        $dummyFile2 = $this->tmpDir.'/error_file2.txt';

        $this->fs->dumpFile($configFile, <<<'YAML'
linter:
  paths:
    - tests/Integration/tmp/error_file1.txt
    - tests/Integration/tmp/error_file2.txt
YAML);

        // 1. Create file 1 with an error. File 2 has no error yet.
        $this->fs->dumpFile($dummyFile1, 'example.com,##.ads');
        // Run normal lint command
        $this->runLintCommand([
            '--config' => $configFile,
            '--cache' => $this->cacheFile,
        ]);

        // Verify dummyFile1 is cached
        $cache = app(Cache::class);
        $cachedData1 = $cache->get(Path::canonicalize($dummyFile1));
        $this->assertNotNull($cachedData1);
        $this->assertNotEmpty($cachedData1['errors'] ?? []);

        // 2. Introduce a new error in dummyFile2
        $this->fs->dumpFile($dummyFile2, 'example.org,##.ads');
        // Run lint command with --generate-baseline
        $baselineFile = base_path('haiku-baseline.yml');
        if (file_exists($baselineFile)) {
            unlink($baselineFile);
        }

        try {
            $this->runLintCommand([
                '--config' => $configFile,
                '--cache' => $this->cacheFile,
                '--generate-baseline' => true,
            ]);

            $this->assertFileExists($baselineFile);
            $baselineContent = \Symfony\Component\Yaml\Yaml::parseFile($baselineFile);
            $ignoredErrors = $baselineContent['ignoreErrors'] ?? [];

            $pathsInBaseline = array_column($ignoredErrors, 'path');

            $relPath1 = Path::makeRelative(Path::canonicalize($dummyFile1), base_path());
            $relPath2 = Path::makeRelative(Path::canonicalize($dummyFile2), base_path());

            $this->assertContains($relPath1, $pathsInBaseline);
            $this->assertContains($relPath2, $pathsInBaseline);

            // Verify dummyFile2 is now also cached
            $cachedData2 = $cache->get(Path::canonicalize($dummyFile2));
            $this->assertNotNull($cachedData2);
            $this->assertNotEmpty($cachedData2['errors'] ?? []);
        } finally {
            if (file_exists($baselineFile)) {
                unlink($baselineFile);
            }
        }
    }

    /**
     * @return \Symfony\Component\Console\Tester\CommandTester
     */
    private function runLintCommand(array $options = [])
    {
        $application = new \Symfony\Component\Console\Application;
        $application->addCommand(app(\Realodix\Haiku\Console\Command\LintCommand::class));
        $command = $application->find('lint');
        $commandTester = new \Symfony\Component\Console\Tester\CommandTester($command);

        $commandTester->execute($options);

        return $commandTester;
    }
}
