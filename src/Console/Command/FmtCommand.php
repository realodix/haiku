<?php

namespace Realodix\Haiku\Console\Command;

use Realodix\Haiku\Config\InvalidConfigurationException;
use Realodix\Haiku\Console\App;
use Realodix\Haiku\Console\CommandOptions;
use Realodix\Haiku\Console\OutputLogger;
use Realodix\Haiku\Formatter\Runner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'format|fix',
    description: 'Sort, combine, and normalize ad-blocking filter lists.',
)]
class FmtCommand
{
    public function __construct(
        private Runner $runner,
        private OutputLogger $logger,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'File or directory to process')]
        ?string $path = null,
        #[Option(description: 'Ignore the cache and process all files')]
        bool $force = false,
        #[Option(description: 'Path to config file')]
        ?string $config = null,
        #[Option(description: 'Path to the cache file')]
        ?string $cache = null,
        #[Option(description: 'Run in parallel')]
        bool $parallel = false,
    ): int {
        if ($config && !is_readable($config)) {
            throw new InvalidConfigurationException(sprintf('Cannot read config file "%s".', $config));
        }

        App::about($io, $config);
        $io->newLine();

        // ---- Execute ----
        $startTime = microtime(true);
        $this->runner->run(
            new CommandOptions(
                cachePath: $cache,
                configFile: $config,
                ignoreCache: $force,
                path: $path,
                parallel: $parallel,
            ),
        );

        $stats = $this->logger->stats();
        if ($stats->allSkipped()) {
            $io->writeln('<info>All files have been processed.</info>');
            $io->newLine();
        } else {
            $io->newLine();
            $io->writeln($stats->summary());
            $io->writeln('------------------');
        }

        $io->writeln(sprintf(
            'Time: %s seconds, Memory: %s',
            round(microtime(true) - $startTime, 2),
            round(memory_get_peak_usage(true) / 1024 / 1024, 2).' MB',
        ));

        return Command::SUCCESS;
    }
}
