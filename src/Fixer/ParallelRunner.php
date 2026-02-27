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

final class ParallelRunner
{
    private LoopInterface $loop;

    public function __construct()
    {
        $this->loop = Loop::get();
    }

    /**
     * @param \Realodix\Haiku\Fixer\Fixer $fixer The Fixer instance
     * @param \Realodix\Haiku\Config\FixerConfig $config The Fixer configuration
     * @param \Realodix\Haiku\Console\CommandOptions $cmdOpt The CLI runtime options
     */
    public function run($fixer, $config, $cmdOpt): void
    {
        $files = $config->paths;
        $fileCount = count($files);

        if ($fileCount === 0) {
            return;
        }

        $server = new SocketServer('127.0.0.1:0', [], $this->loop);
        $address = $server->getAddress();
        $cores = (new CpuCoreCounter)->getCount();
        $poolSize = min($cores, $fileCount);

        $pendingFiles = $files;
        $processedCount = 0;

        $server->on(
            'connection',
            function (ConnectionInterface $connection) use (&$pendingFiles, &$processedCount, $cmdOpt, $fixer, $fileCount) {
                $decoder = new Decoder($connection);
                $encoder = new Encoder($connection);

                $sendNextTask = function () use ($encoder, &$pendingFiles, $cmdOpt, $fixer, $connection) {
                    if (empty($pendingFiles)) {
                        $connection->end();

                        return false;
                    }

                    $file = array_shift($pendingFiles);
                    $encoder->write([
                        'path' => $file,
                        'cachePath' => $cmdOpt->cachePath,
                        'configFile' => $cmdOpt->configFile,
                        'ignoreCache' => $cmdOpt->ignoreCache,
                        'hashPrefix' => $fixer->getHashPrefix(),
                    ]);

                    return true;
                };

                $decoder->on('data', function ($data) use ($sendNextTask, &$processedCount, $fixer, $fileCount) {
                    $data = (array) $data;
                    $fixer->record($data);

                    $processedCount++;

                    if (!$sendNextTask() && $processedCount === $fileCount) {
                        $this->loop->stop();
                    }
                });

                $sendNextTask();
            },
        );

        for ($i = 0; $i < $poolSize; $i++) {
            $this->spawnPersistentWorker($address, $cmdOpt);
        }

        $this->loop->run();
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
