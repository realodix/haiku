<?php

namespace Realodix\Haiku\Test;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Realodix\Haiku\Config\FixerConfig;
use Realodix\Haiku\Console\BuildCommand;
use Realodix\Haiku\Console\FixCommand;
use Realodix\Haiku\Fixer\Processor;
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
    }

    protected function fix(array $value, array $flags = []): mixed
    {
        $flags = array_merge(app(FixerConfig::class)->flags, $flags);

        return app(Processor::class)->process($value, $flags);
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

        $commandTester->execute(array_merge([
            '--cache' => isset($options['--cache']) ? $options['--cache'] : $this->cacheFile,
        ], $options));

        return $commandTester;
    }

    protected function assertFilter(string $expectedFile, string $actualFile, array $options = [])
    {
        $processingFile = Path::join($this->tmpDir, basename($actualFile));
        $this->fs->copy($actualFile, $processingFile, true);

        $this->runFixCommand(array_merge([
            '--path' => $processingFile,
        ], $options));

        $this->assertFileEquals($expectedFile, $processingFile);
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
