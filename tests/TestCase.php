<?php

namespace Realodix\Haiku\Test;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Realodix\Haiku\Config\FixerConfig;
use Realodix\Haiku\Config\LinterConfig;
use Realodix\Haiku\Console\Command\BuildCommand;
use Realodix\Haiku\Console\Command\FixCommand;
use Realodix\Haiku\Fixer\Fixer;
use Realodix\Haiku\Linter\Rules\Rule;
use Realodix\Haiku\Linter\Util;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

abstract class TestCase extends BaseTestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    public $tmpDir = __DIR__.'/Integration/tmp';

    public $cacheFile = __DIR__.'/Integration/tmp/cache.json';

    protected Filesystem $fs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fs = new Filesystem;

        // In the test environment, we bind the OutputInterface to a silent,
        // buffered output so that command output isn't printed during tests.
        app()->singleton(
            \Symfony\Component\Console\Output\OutputInterface::class,
            \Symfony\Component\Console\Output\BufferedOutput::class,
        );

        app()->instance(FixerConfig::class, new FixerConfig);
        app()->instance(LinterConfig::class, new LinterConfig);
    }

    protected function applyFlags(array $flags = [])
    {
        app(FixerConfig::class)->flags = array_merge([
            'fmode' => true,
            'no_legacy_ext_selectors' => false,
            'no_legacy_remove_action' => false,
        ], $flags);
    }

    protected function fix(array $value, array $flags = []): mixed
    {
        $this->applyFlags($flags);

        return app(Fixer::class)->fix($value);
    }

    protected function runBuildCommand(array $options = [])
    {
        $application = new Application;
        $application->addCommand(app(BuildCommand::class));
        $command = $application->find('build');
        $commandTester = new CommandTester($command);

        $commandTester->execute(array_merge([
            '--config' => 'tests/Integration/Builder/haiku.yml',
            '--force' => true,
        ], $options));
    }

    protected function runFixCommand(array $options = []): CommandTester
    {
        $application = new Application;
        $application->addCommand(app(FixCommand::class));
        $command = $application->find('fix');
        $commandTester = new CommandTester($command);

        $this->applyFlags();

        $commandTester->execute(array_merge([
            '--cache' => isset($options['--cache']) ? $options['--cache'] : $this->cacheFile,
        ], $options));

        return $commandTester;
    }

    /**
     * Helper to run analysis rules on a given set of lines.
     *
     * @param list<string> $lines
     * @param list<array{0: int, 1: string, 2?: string}> $expectedErrors
     * @param list<class-string<Rule>>|null $onlyRules Optional list of rule class names to run
     */
    protected function analyse(array $lines, array $expectedErrors = [], ?array $onlyRules = null): void
    {
        app(LinterConfig::class)->rules = [
            'no_short_rules' => 3,
        ];
        $rules = $this->initLinterRules($onlyRules);

        // Run all selected rules
        $actualErrors = [];
        foreach ($rules as $rule) {
            $errors = $rule->check($lines);
            $actualErrors = array_merge($actualErrors, $errors);
        }

        // === Formatting helper ===
        // $strictlyTypedSprintf = static function (int $line, string $message, ?string $tip = null): string {
        $strictlyTypedSprintf = static function (int $line, string $message): string {
            $message = sprintf('%02d: %s', $line, $message);
            // if ($tip !== null) {
            //     $message .= "\n    💡 ".$tip;
            // }

            return $message;
        };

        // === Sort expected ===
        usort($expectedErrors, static function ($a, $b) {
            if ($a[0] !== $b[0]) {
                return $a[0] <=> $b[0];
            }

            if ($a[1] !== $b[1]) {
                return strcmp($a[1], $b[1]);
            }

            // tip()
            // if (!isset($a[2])) {
            //     return isset($b[2]) ? 1 : 0;
            // }

            // if (!isset($b[2])) {
            //     return -1;
            // }

            // return strcmp($a[2], $b[2]);
        });

        $expectedErrors = array_map(
            static fn(array $error): string => $strictlyTypedSprintf(
                $error[0],
                $error[1],
                // $error[2] ?? null,
            ),
            $expectedErrors,
        );

        // === Sort actual ===
        usort($actualErrors, static function ($a, $b) {
            if ($a['line'] !== $b['line']) {
                return $a['line'] <=> $b['line'];
            }

            if ($a['message'] !== $b['message']) {
                return strcmp($a['message'], $b['message']);
            }

            // $aTip = $a['tip'] ?? null;
            // $bTip = $b['tip'] ?? null;

            // if ($aTip === null) {
            //     return $bTip === null ? 0 : 1;
            // }

            // if ($bTip === null) {
            //     return -1;
            // }

            // return strcmp($aTip, $bTip);
        });

        $actualErrors = array_map(
            static function (array $error) use ($strictlyTypedSprintf): string {
                return $strictlyTypedSprintf(
                    $error['line'],
                    $error['message'],
                    // $error['tip'] ?? null,
                );
            },
            $actualErrors,
        );

        // === Compare ===
        $expectedErrorsString = implode("\n", $expectedErrors)."\n";
        $actualErrorsString = implode("\n", $actualErrors)."\n";

        $this->assertSame($expectedErrorsString, $actualErrorsString);
    }

    /**
     * Finds and instantiates all rules in the Rules directory.
     *
     * @return list<Rule>
     */
    private function initLinterRules($onlyRules): array
    {
        if ($onlyRules !== null) {
            if ($onlyRules === []) {
                $this->fail('$onlyRules cannot be empty.');
            }

            $rules = [];

            foreach ($onlyRules as $class) {
                if (!class_exists($class)) {
                    $this->fail("Rule {$class} does not exist.");
                }

                // if (!is_a($class, \Realodix\Haiku\Linter\Rules\Rule::class, true)) {
                //     $this->fail("Rule {$class} must implement Rule interface.");
                // }

                $rules[] = app($class);
            }
        } else {
            $rules = Util::loadLinterRules();
        }

        // Safety: no rules at all
        if (empty($rules)) {
            $this->fail('No rules available for analysis.');
        }

        return $rules;
    }

    /**
     * Helper to call private/protected methods for testing
     */
    protected function callPrivateMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Helper to get private/protected properties for testing
     */
    protected function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);

        return $property->getValue($object);
    }

    protected function tearDown(): void
    {
        if ($this->fs->exists($this->tmpDir)) {
            $files = array_merge(
                glob(Path::join($this->tmpDir, '/*.txt')),
                glob(Path::join($this->tmpDir, '/*.json')),
                glob(Path::join($this->tmpDir, '/.*.json')),
                glob(Path::join($this->tmpDir, '/*.yml')),
            );

            if ($files) {
                $this->fs->remove($files);
            }
        }
    }
}
