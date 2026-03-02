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
use Realodix\Haiku\Fixer\FileFixer;
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

            $fileFixer = app(FileFixer::class);
            $config = null;

            /** @param _WorkerPayload $data */
            $decoder->on('data', function ($data) use ($encoder, $fileFixer, &$config) {
                $data = (array) $data;

                // Lazily initialize config & cache ONCE
                if ($config === null) {
                    $cmdOpt = new CommandOptions(
                        cachePath: $data['cachePath'] ?? null,
                        configFile: $data['configFile'] ?? null,
                        ignoreCache: $data['ignoreCache'] ?? false,
                        path: $data['path'],
                    );

                    $config = app(Config::class)->fixer($cmdOpt);

                    // Important: NO pruning in worker
                    app(Cache::class)->prepareForRun($config->paths, $cmdOpt, pruning: false);
                }

                $result = $fileFixer->fix(
                    $data['path'],
                    $config,
                    $data['hashPrefix'] ?? '',
                );

                // Send plain payload back to main process
                $encoder->write([
                    'path' => $result->path,
                    'status' => $result->status,
                    'hash' => $result->hash,
                ]);
            });
        });

        $loop->run();
    }
}
