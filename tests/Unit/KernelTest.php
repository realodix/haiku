<?php

namespace Realodix\Haiku\Test\Unit;

use PHPUnit\Framework\Attributes as PHPUnit;
use Realodix\Haiku\Console\Kernel;
use Realodix\Haiku\Test\TestCase;
use Symfony\Component\Console\Application;

class KernelTest extends TestCase
{
    #[PHPUnit\TestWith([\Realodix\Haiku\Cache\Cache::class])]
    #[PHPUnit\TestWith([\Realodix\Haiku\Config\Config::class])]
    #[PHPUnit\TestWith([\Realodix\Haiku\Config\FixerConfig::class])]
    #[PHPUnit\TestWith([\Realodix\Haiku\Config\LinterConfig::class])]
    public function testregisterServices($class)
    {
        $kernel = new Kernel;
        $container = $this->getPrivateProperty($kernel, 'app');

        $this->assertTrue($container->bound($class));
        $this->assertSame($container->make($class), $container->make($class));
    }

    public function testRegisterCommands()
    {
        $kernel = new Kernel;
        $commands = $this->getPrivateProperty($kernel, 'commands');

        $applicationMock = \Mockery::mock(Application::class);
        $applicationMock->expects('addCommand')
            ->times(count($commands))
            ->with(\Mockery::type(\Symfony\Component\Console\Command\Command::class));

        $this->callPrivateMethod($kernel, 'registerCommands', [$applicationMock]);
    }
}
