<?php

namespace Realodix\Haiku\Test;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Realodix\Haiku\Config\FixerConfig;
use Realodix\Haiku\Console\Command\BuildCommand;
use Realodix\Haiku\Console\Command\FixCommand;
use Realodix\Haiku\Fixer\Fixer;
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
    }

    protected function applyFlags(array $flags = [])
    {
        app(FixerConfig::class)->flags = array_merge([
            'fmode' => true,
            'exact_attr_to_css_selector' => false,
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
            );

            if ($files) {
                $this->fs->remove($files);
            }
        }
    }
}
