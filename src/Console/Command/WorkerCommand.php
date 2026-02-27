<?php

namespace Realodix\Haiku\Console\Command;

use Clue\React\NDJson\Decoder;
use Clue\React\NDJson\Encoder;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Config\Config;
use Realodix\Haiku\Console\CommandOptions;
use Realodix\Haiku\Fixer\Fixer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-type _WorkerPayload array{
 *   path: string,
 *   cachePath?: string,
 *   configFile?: string,
 *   ignoreCache?: bool,
 *   hashPrefix?: string
 * }
 */
#[AsCommand(
    name: 'worker',
    description: 'Internal command for running Fixer in parallel.',
    hidden: true,
)]
class WorkerCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('socket', null, InputOption::VALUE_REQUIRED, 'Socket address to connect to for tasks')
            ->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Path to config file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Silence worker output to prevent clashing with main process
        app()->instance(OutputInterface::class, new NullOutput);

        $socket = $input->getOption('socket');
        if (!$socket) {
            throw new \InvalidArgumentException('The --socket option is required.');
        }

        $this->runPersistent($socket);

        return Command::SUCCESS;
    }

    /**
     * Run the persistent worker for the given socket address.
     *
     * This function sets up an event loop to continuously receive tasks from the main
     * process over the given socket address, and then processes them.
     *
     * @param string $address The socket address to connect to for tasks.
     */
    private function runPersistent(string $address): void
    {
        $loop = Loop::get();
        $connector = new Connector($loop);

        $connector->connect($address)->then(function (ConnectionInterface $connection) {
            $decoder = new Decoder($connection);
            $encoder = new Encoder($connection);

            $connection->on('close', fn() => exit(0));

            $fixer = app(Fixer::class);
            $config = null; // Cache the configuration to avoid redundant parsing for every file

            /** @param _WorkerPayload $data  */
            $decoder->on('data', function ($data) use ($encoder, $fixer, &$config) {
                $data = (array) $data;

                if ($config === null) {
                    $cmdOpt = new CommandOptions(
                        cachePath: $data['cachePath'] ?? null,
                        configFile: $data['configFile'] ?? null,
                        ignoreCache: $data['ignoreCache'] ?? false,
                        path: $data['path'],
                    );

                    $config = app(Config::class)->fixer($cmdOpt);
                    app(Cache::class)->prepareForRun($config->paths, $cmdOpt, pruning: false);
                }

                if (isset($data['hashPrefix'])) {
                    $fixer->setHashPrefix($data['hashPrefix']);
                }

                $result = $fixer->processFile($data['path'], $config);
                $encoder->write($result);
            });
        });

        $loop->run();
    }
}
