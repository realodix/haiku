<?php

namespace Realodix\Haiku\Test\Unit;

use Realodix\Haiku\App;
use Realodix\Haiku\Console\Command\FixCommand;
use Realodix\Haiku\Console\Kernel;
use Realodix\Haiku\Test\TestCase;
use Symfony\Component\Console\Application;

class KernelTest extends TestCase
{
    public function testBootstrap()
    {
        $providerMock = \Mockery::mock(App::class);
        $providerMock->expects('register');

        $kernel = new Kernel;
        $container = $this->getPrivateProperty($kernel, 'app');
        $container->instance(App::class, $providerMock);
        $kernel->bootstrap();

        // App class binds FixCommand to the container.
        // We can check if the container can resolve it.
        $command = $this->getPrivateProperty($kernel, 'app')->make(FixCommand::class);
        $this->assertInstanceOf(FixCommand::class, $command);
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
