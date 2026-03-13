<?php

namespace Realodix\Haiku\Test\Unit;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Config\InvalidConfigurationException;
use Realodix\Haiku\Test\TestCase;

class CommandTest extends TestCase
{
    #[PHPUnit\Test]
    public function builder_custom_config_not_found(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The configuration file does not exist.');

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
        $this->expectExceptionMessage('Cannot read config file "notfound.yml".');

        $this->runFixCommand(['--config' => 'notfound.yml']);
    }
}
