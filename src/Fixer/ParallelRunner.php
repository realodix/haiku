<?php

namespace Realodix\Haiku\Fixer;

use Clue\React\NDJson\Decoder;
use Clue\React\NDJson\Encoder;
use Fidry\CpuCoreCounter\CpuCoreCounter;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

/**
 * @phpstan-import-type _WorkerPayload from \Realodix\Haiku\Console\Command\WorkerCommand
 */
final class ParallelRunner
{
    private LoopInterface $loop;

    /** @var array<int, string> */
    private array $pendingFiles = [];

    private int $processedCount = 0;

    private int $fileCount = 0;

    public function __construct()
    {
        $this->loop = Loop::get();
    }

    /**
     * Run the Fixer in parallel.
     *
     * @param \Realodix\Haiku\Fixer\Fixer $fixer The Fixer instance
     * @param \Realodix\Haiku\Config\FixerConfig $config The Fixer configuration
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt The CLI runtime options
     */
    public function run($fixer, $config, $cmdOpt): void
    {
        $this->pendingFiles = $config->paths;
        $this->fileCount = count($this->pendingFiles);

        if ($this->fileCount === 0) {
            return;
        }

        $server = new SocketServer('127.0.0.1:0', [], $this->loop);
        $address = $server->getAddress();
        $poolSize = min((new CpuCoreCounter)->getCount(), $this->fileCount);

        $server->on(
            'connection',
            function (ConnectionInterface $connection) use ($cmdOpt, $fixer) {
                $this->handleConnection($connection, $cmdOpt, $fixer);
            },
        );

        for ($i = 0; $i < $poolSize; $i++) {
            $this->spawnPersistentWorker($address, $cmdOpt);
        }

        $this->loop->run();
    }

    /**
     * Handles an incoming worker connection.
     *
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt
     * @param \Realodix\Haiku\Fixer\Fixer $fixer
     */
    private function handleConnection(ConnectionInterface $connection, $cmdOpt, $fixer): void
    {
        $decoder = new Decoder($connection);
        $encoder = new Encoder($connection);

        $sendNextTask = function () use ($encoder, $cmdOpt, $fixer, $connection) {
            if (empty($this->pendingFiles)) {
                $connection->end();

                return false;
            }

            $file = array_shift($this->pendingFiles);
            $encoder->write([
                'path' => $file,
                'cachePath' => $cmdOpt->cachePath,
                'configFile' => $cmdOpt->configFile,
                'ignoreCache' => $cmdOpt->ignoreCache,
                'hashPrefix' => $fixer->hashPrefix,
            ]);

            return true;
        };

        /** * @param _WorkerPayload $data */
        $decoder->on('data', function ($data) use ($sendNextTask, $fixer) {
            $fixer->record((array) $data);

            $this->processedCount++;

            if (!$sendNextTask() && $this->processedCount === $this->fileCount) {
                $this->loop->stop();
            }
        });

        $sendNextTask();
    }

    /**
     * Spawns a persistent worker process.
     *
     * This function will construct a command to run the worker process, and then start it.
     * The command is constructed from the haiku binary path, the address of the socket
     * server, and the path to the configuration file (if provided).
     *
     * @param string $address The address of the socket server to connect to.
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt The command options to use when
     *                                                       constructing the command.
     */
    private function spawnPersistentWorker(string $address, $cmdOpt): void
    {
        $command = sprintf(
            'php %s worker --socket=%s',
            escapeshellarg(base_path('haiku')),
            escapeshellarg($address),
        );

        if ($cmdOpt->configFile) {
            $command .= ' --config='.escapeshellarg($cmdOpt->configFile);
        }

        $process = new Process($command, null, null, []);
        $process->start($this->loop);
    }
}
