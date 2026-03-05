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
 * @phpstan-import-type _FixResult from \Realodix\Haiku\Fixer\Fixer
 * @phpstan-import-type _WorkerPayload from \Realodix\Haiku\Fixer\ParallelRunner
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
     * process over the given socket address, processes them, and sends back the results.
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

            // Exit cleanly when main process closes the socket
            $connection->on('close', static fn() => exit(0));

            $fixer = app(Fixer::class);
            $config = null; // Cache the configuration to avoid redundant parsing for every file

            $decoder->on('data', function ($data) use ($encoder, $fixer, &$config) {
                /** @var _WorkerPayload $data */
                $payload = (array) $data;

                try {
                    // Lazily initialize config & cache ONCE
                    if ($config === null) {
                        $cmdOpt = new CommandOptions(
                            cachePath: $payload['cachePath'],
                            configFile: $payload['configFile'],
                            ignoreCache: $payload['ignoreCache'],
                            path: $payload['path'],
                        );

                        $config = app(Config::class)->fixer($cmdOpt);

                        // Important: NO pruning in worker
                        app(Cache::class)->prepareForRun($config->paths, $cmdOpt, pruning: false);
                    }

                    $result = $fixer->fixFile($payload['path'], $config);

                    // Send result back to main process
                    /** @var _FixResult */
                    $resultData = [
                        'status' => $result['status'],
                        'path' => $result['path'],
                        'hash' => $result['hash'] ?? null,
                    ];
                    $encoder->write($resultData);
                } catch (\Throwable $e) {
                    /** @var _FixResult */
                    $resultData = [
                        'status' => 'error',
                        'path' => $payload['path'],
                        'message' => $e->getMessage(),
                    ];
                    $encoder->write($resultData);
                }
            });
        });

        $loop->run();
    }
}
