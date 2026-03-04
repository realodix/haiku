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
 * @phpstan-import-type _FixResult from \Realodix\Haiku\Fixer\Fixer
 * @phpstan-type _WorkerPayload array{
 *   path: string,
 *   cachePath: string,
 *   configFile: string,
 *   ignoreCache: bool,
 *   hashPrefix: string
 * }
 */
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
     * @return _FixResult[]
     */
    public function run($fixer, $config, $cmdOpt): array
    {
        $pendingFiles = $config->paths;
        $fileCount = count($pendingFiles);
        $processedCount = 0;
        $results = [];

        if ($fileCount === 0) {
            return [];
        }

        // 2. Setup Socket Server
        $server = new SocketServer('127.0.0.1:0', [], $this->loop);
        $server->on(
            'connection',
            function (ConnectionInterface $connection) use ($cmdOpt, $fixer, &$pendingFiles, &$processedCount, &$results, $fileCount) {
                $decoder = new Decoder($connection);
                $encoder = new Encoder($connection);

                // 1. Dispatch next file to worker
                $sendNextTask = function () use ($encoder, $cmdOpt, $fixer, $connection, &$pendingFiles) {
                    // Stop sending tasks if queue is empty
                    if (empty($pendingFiles)) {
                        $connection->end();

                        return false;
                    }

                    /** @var _WorkerPayload */
                    $workerPayload = [
                        'path' => array_shift($pendingFiles),
                        'cachePath' => $cmdOpt->cachePath,
                        'configFile' => $cmdOpt->configFile,
                        'ignoreCache' => $cmdOpt->ignoreCache,
                        'hashPrefix' => $fixer->hashPrefix,
                    ];
                    $encoder->write($workerPayload);

                    return true;
                };

                // 2. Handle worker result
                $decoder->on('data', function ($data) use ($sendNextTask, $fixer, &$processedCount, &$results, $fileCount) {
                    /** @var _FixResult */
                    $result = (array) $data;

                    $fixer->record($result);
                    $results[] = $result;
                    $processedCount++;

                    // If no more tasks AND all files processed, stop event loop
                    if (!$sendNextTask() && $processedCount === $fileCount) {
                        $this->loop->stop();
                    }
                });

                // Send initial task to worker
                $sendNextTask();
            },
        );

        // 3. Spawn persistent worker processes
        $poolSize = min((new CpuCoreCounter)->getCount(), $fileCount);
        $address = $server->getAddress();
        for ($i = 0; $i < $poolSize; $i++) {
            $this->spawnPersistentWorker($address, $cmdOpt);
        }

        // 4. Start event loop
        $this->loop->run();

        return $results;
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
