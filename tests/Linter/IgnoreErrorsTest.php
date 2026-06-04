<?php

namespace Realodix\Haiku\Test\Linter;

use PHPUnit\Framework\Attributes as PHPUnit;
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
    - path: path_foo.txt
YAML);

        // This triggers DomainCheck: "Unexpected empty domain.."
        $this->fs->dumpFile($dummyFile, 'example.com,##.ads');

        $linter = app(Linter::class);
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
        $this->assertContains(
            'Ignored error pattern in path path_foo.txt was not matched in reported errors.',
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
        $dummyFile_1 = $this->tmpDir.'/ignored2_a.txt';
        $dummyFile_2 = $this->tmpDir.'/ignored2_b.txt';

        $this->fs->dumpFile($configFile, <<<'YAML'
linter:
  paths:
    - tests/Integration/tmp/ignored2_a.txt
    - tests/Integration/tmp/ignored2_b.txt
  rules:
    no_extra_blank_lines: false
  ignoreErrors:
    - '#^Unexpected empty domain#'
    - path: '#_b#'
    - 'foo-string'
YAML);

        $this->fs->dumpFile($dummyFile_1, ',example.com##.ads');
        $this->fs->dumpFile($dummyFile_2, 'example.com,example.com###ads');

        $linter = app(Linter::class);
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

    #[PHPUnit\RequiresOperatingSystemFamily('Windows')]
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

        $linter = app(Linter::class);
        $cmdOpt = new CommandOptions(configFile: 'tests/Integration/tmp/haiku3.yml');

        $errorReporter = $linter->run($cmdOpt);

        // Ensure that the actual error was correctly ignored from the file's errors
        $fileErrors = $errorReporter->getErrors();
        $this->assertEmpty($fileErrors);
    }

    #[PHPUnit\RequiresOperatingSystemFamily('Windows')]
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

        $linter = app(Linter::class);
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

    #[PHPUnit\Test]
    #[PHPUnit\DataProvider('normalizeDataProvider')]
    public function normalizeIgnoreErrors(array $input, array $expected): void
    {
        $ignoredErrors = new IgnoredErrors($input);

        $reflection = new \ReflectionClass($ignoredErrors);
        $property = $reflection->getProperty('ignorePatterns');

        $this->assertSame($expected, $property->getValue($ignoredErrors));
    }

    public static function normalizeDataProvider(): array
    {
        return [
            'string pattern' => [
                ['foo'],
                ['foo'],
            ],
            'message only' => [
                [['message' => 'foo']],
                [['message' => 'foo']],
            ],
            'messages only' => [
                [['messages' => ['foo', 'bar']]],
                [['message' => 'foo'], ['message' => 'bar']],
            ],
            'message and messages' => [
                [['message' => 'foo', 'messages' => ['bar', 'baz']]],
                [['message' => 'foo'], ['message' => 'bar'], ['message' => 'baz']],
            ],
            'path only' => [
                [['path' => 'foo.txt']],
                [['path' => 'foo.txt']],
            ],
            'paths only' => [
                [['paths' => ['foo.txt', 'bar.txt']]],
                [['path' => 'foo.txt'], ['path' => 'bar.txt']],
            ],
            'path and paths' => [
                [['path' => 'foo.txt', 'paths' => ['bar.txt', 'baz.txt']]],
                [['path' => 'foo.txt'], ['path' => 'bar.txt'], ['path' => 'baz.txt']],
            ],
            'message and path' => [
                [['message' => 'msg', 'path' => 'file.txt']],
                [['message' => 'msg', 'path' => 'file.txt']],
            ],
            'messages and path' => [
                [['messages' => ['msg1', 'msg2'], 'path' => 'file.txt']],
                [
                    ['message' => 'msg1', 'path' => 'file.txt'],
                    ['message' => 'msg2', 'path' => 'file.txt'],
                ],
            ],
            'message and paths' => [
                [['message' => 'msg', 'paths' => ['file1.txt', 'file2.txt']]],
                [
                    ['message' => 'msg', 'path' => 'file1.txt'],
                    ['message' => 'msg', 'path' => 'file2.txt'],
                ],
            ],
            'messages and paths' => [
                [['messages' => ['msg1', 'msg2'], 'paths' => ['file1.txt', 'file2.txt']]],
                [
                    ['message' => 'msg1', 'path' => 'file1.txt'],
                    ['message' => 'msg1', 'path' => 'file2.txt'],
                    ['message' => 'msg2', 'path' => 'file1.txt'],
                    ['message' => 'msg2', 'path' => 'file2.txt'],
                ],
            ],
            'with extra keys (count, etc)' => [
                [['message' => 'msg', 'path' => 'file.txt', 'count' => 5]],
                [['count' => 5, 'message' => 'msg', 'path' => 'file.txt']],
            ],
            'multiple paths with count' => [
                [['message' => 'msg', 'paths' => ['file1.txt', 'file2.txt'], 'count' => 5]],
                [
                    ['count' => 5, 'message' => 'msg', 'path' => 'file1.txt'],
                    ['count' => 5, 'message' => 'msg', 'path' => 'file2.txt'],
                ],
            ],
            'messages as string' => [
                [['messages' => 'foo']],
                [['message' => 'foo']],
            ],
            'paths as string' => [
                [['paths' => 'foo.txt']],
                [['path' => 'foo.txt']],
            ],
            'all combinations' => [
                [
                    [
                        'message' => 'm1',
                        'messages' => ['m2'],
                        'path' => 'p1',
                        'paths' => ['p2'],
                    ],
                ],
                [
                    ['message' => 'm1', 'path' => 'p1'],
                    ['message' => 'm1', 'path' => 'p2'],
                    ['message' => 'm2', 'path' => 'p1'],
                    ['message' => 'm2', 'path' => 'p2'],
                ],
            ],
            'empty entry' => [
                [[]],
                [],
            ],
            'entry with neither message nor path' => [
                [['count' => 5]],
                [],
            ],
        ];
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

    #[PHPUnit\Test]
    public function matchingWithCombinations(): void
    {
        $input = [
            [
                'messages' => ['foo', 'bar'],
                'paths' => ['file1.txt', 'file2.txt'],
            ],
        ];

        $ignoredErrors = new IgnoredErrors($input);

        // Matches
        $this->assertTrue($ignoredErrors->shouldIgnore('file1.txt', 'foo message'));
        $this->assertTrue($ignoredErrors->shouldIgnore('file2.txt', 'foo message'));
        $this->assertTrue($ignoredErrors->shouldIgnore('file1.txt', 'bar message'));
        $this->assertTrue($ignoredErrors->shouldIgnore('file2.txt', 'bar message'));

        // Non-matches
        $this->assertFalse($ignoredErrors->shouldIgnore('file3.txt', 'foo message'));
        $this->assertFalse($ignoredErrors->shouldIgnore('file1.txt', 'baz message'));
    }
}
