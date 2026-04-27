<?php

namespace Realodix\Haiku\Test\Linter;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Console\CommandOptions;
use Realodix\Haiku\Linter\ErrorReporter;
use Realodix\Haiku\Linter\IgnoredErrors;
use Realodix\Haiku\Linter\Linter;
use Realodix\Haiku\Test\TestCase;

class IgnoreErrorsTest extends TestCase
{
    #[PHPUnit\Test]
    public function reportUnmatchedGlobalErrors(): void
    {
        $configFile = $this->tmpDir.'/haiku.yml';
        $dummyFile = $this->tmpDir.'/ignored.txt';

        $this->fs->dumpFile($configFile, <<<'YAML'
linter:
  paths:
    - tests/Integration/tmp/ignored.txt
  rules:
    no_extra_blank_lines: false
  ignoreErrors:
    - messages:
      - '#^Unexpected empty domain#'
      - 'foo'
    - path: tests/Integration/tmp/ignored.txt
      message: 'bar'
YAML);

        // This triggers DomainCheck: "Unexpected empty domain.."
        $this->fs->dumpFile($dummyFile, 'example.com,##.ads');

        $linter = new Linter(app(Config::class));
        $cmdOpt = new CommandOptions(configFile: 'tests/Integration/tmp/haiku.yml');

        $errorReporter = $linter->run($cmdOpt);

        $globalErrors = $errorReporter->getGlobalErrors();
        $this->assertContains(
            'Ignored error pattern foo was not matched in reported errors.',
            $globalErrors,
        );
        $this->assertContains(
            'Ignored error pattern bar in path tests/Integration/tmp/ignored.txt was not matched in reported errors.',
            $globalErrors,
        );

        // Ensure that the matched error pattern is NOT in the reported unmatched errors list
        $this->assertNotContains(
            'Ignored error pattern Unexpected empty domain in cosmetic rule was not matched in reported errors.',
            $globalErrors,
        );

        // Ensure that the actual error was correctly ignored from the file's errors
        $fileErrors = $errorReporter->getErrors();
        $this->assertEmpty($fileErrors);
    }

    #[PHPUnit\Test]
    public function stringIgnorePattern(): void
    {
        $configFile = $this->tmpDir.'/haiku2.yml';
        $dummyFile = $this->tmpDir.'/ignored2.txt';

        $this->fs->dumpFile($configFile, <<<'YAML'
linter:
  paths:
    - tests/Integration/tmp/ignored2.txt
  rules:
    no_extra_blank_lines: false
  ignoreErrors:
    - '#^Unexpected empty domain#'
    - 'foo-string'
YAML);

        $this->fs->dumpFile($dummyFile, ',example.com##.ads');

        $linter = new Linter(app(Config::class));
        $cmdOpt = new CommandOptions(configFile: 'tests/Integration/tmp/haiku2.yml');

        $errorReporter = $linter->run($cmdOpt);
        $globalErrors = $errorReporter->getGlobalErrors();

        $this->assertContains(
            'Ignored error pattern foo-string was not matched in reported errors.',
            $globalErrors,
        );

        $fileErrors = $errorReporter->getErrors();
        $this->assertEmpty($fileErrors);
    }

    #[PHPUnit\Test]
    public function pathNormalization(): void
    {
        $configFile = $this->tmpDir.'/haiku3.yml';
        $dummyFile = $this->tmpDir.'/ignored3.txt';

        $this->fs->dumpFile($configFile, <<<'YAML'
linter:
  paths:
    - tests/Integration/tmp/ignored3.txt
  rules:
    no_extra_blank_lines: false
  ignoreErrors:
    - path: 'tests\Integration\tmp\ignored3.txt'
YAML);

        $this->fs->dumpFile($dummyFile, 'example.com,##.ads');

        $linter = new Linter(app(Config::class));
        $cmdOpt = new CommandOptions(configFile: 'tests/Integration/tmp/haiku3.yml');

        $errorReporter = $linter->run($cmdOpt);

        // Ensure that the actual error was correctly ignored from the file's errors
        $fileErrors = $errorReporter->getErrors();
        $this->assertEmpty($fileErrors);
    }

    #[PHPUnit\Test]
    public function pathsNormalization(): void
    {
        $configFile = $this->tmpDir.'/haiku4.yml';
        $dummyFile = $this->tmpDir.'/ignored4.txt';

        $this->fs->dumpFile($configFile, <<<'YAML'
linter:
  paths:
    - tests/Integration/tmp/ignored4.txt
  rules:
    no_extra_blank_lines: false
  ignoreErrors:
    - paths:
        - 'tests\Integration\tmp\ignored4.txt'
        - 'foo\bar'
YAML);

        $this->fs->dumpFile($dummyFile, 'example.com,##.ads');

        $linter = new Linter(app(Config::class));
        $cmdOpt = new CommandOptions(configFile: 'tests/Integration/tmp/haiku4.yml');

        $errorReporter = $linter->run($cmdOpt);

        $fileErrors = $errorReporter->getErrors();
        $this->assertEmpty($fileErrors);
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
}
