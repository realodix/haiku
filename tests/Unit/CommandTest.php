<?php

namespace Realodix\Haiku\Test\Unit;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Config\InvalidConfigurationException;
use Realodix\Haiku\Test\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class CommandTest extends TestCase
{
    #[PHPUnit\Before]
    protected function setUpHaikuConfig(): void
    {
        if (file_exists(base_path('haiku.yml'))) {
            $fs = new Filesystem;
            $fs->rename(base_path('haiku.yml'), base_path('haiku.yml.tmp'));
        }
    }

    #[PHPUnit\After]
    protected function tearDownHaikuConfig(): void
    {
        if (file_exists(base_path('haiku.yml.tmp'))) {
            $fs = new Filesystem;
            $fs->rename(base_path('haiku.yml.tmp'), base_path('haiku.yml'));
        }
    }

    #[PHPUnit\Test]
    public function builder_needs_config(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIs('The configuration file does not exist.');

        $application = new \Symfony\Component\Console\Application;
        $application->addCommand(app(\Realodix\Haiku\Console\Command\BuildCommand::class));
        $command = $application->find('build');
        $commandTester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $commandTester->execute([]);
    }

    #[PHPUnit\Test]
    public function builder_custom_config_not_found(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIs('Cannot read config file "notfound.yml".');

        $this->runBuildCommand(['--config' => 'notfound.yml']);
    }

    #[PHPUnit\Test]
    public function fixer_custom_path_not_found(): void
    {
        $commandTester = $this->runFixCommand(['--path' => 'notfound.yml']);

        $this->assertStringContainsString('Error: 1', $commandTester->getDisplay());
    }

    #[PHPUnit\Test]
    public function fixer_custom_config_not_found(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIs('Cannot read config file "notfound.yml".');

        $this->runFixCommand(['--config' => 'notfound.yml']);
    }
}
