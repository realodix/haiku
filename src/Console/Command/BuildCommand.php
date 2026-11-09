<?php

namespace Realodix\Haiku\Console\Command;

use Realodix\Haiku\Bundler\Builder;
use Realodix\Haiku\Config\InvalidConfigurationException;
use Realodix\Haiku\Console\App;
use Realodix\Haiku\Console\CommandOptions;
use Realodix\Haiku\Console\OutputLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'build',
    description: 'Compiles multiple filter list sources into single unified output files.',
)]
class BuildCommand
{
    public function __construct(
        private Builder $builder,
        private OutputLogger $logger,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Ignore cache and rebuild all sources')]
        bool $force = false,
        #[Option(description: 'Path to config file')]
        ?string $config = null,
    ): int {
        if ($config && !is_readable($config)) {
            throw new InvalidConfigurationException(sprintf('Cannot read config file "%s".', $config));
        }

        App::about($io, $config);
        $io->newLine();

        // ---- Execute ----
        $startTime = microtime(true);
        $io->writeln('Building...');
        $this->builder->handle(
            new CommandOptions(
                configFile: $config,
                ignoreCache: $force,
            ),
        );

        if ($this->logger->stats()->allSkipped()) {
            $io->writeln('<info>[OK] No modified files detected. Nothing to process.</info>');
        }

        $io->newLine();
        $io->writeln(sprintf(
            'Time: %s seconds, Memory: %s',
            round(microtime(true) - $startTime, 2),
            round(memory_get_peak_usage(true) / 1024 / 1024, 2).' MB',
        ));

        return Command::SUCCESS;
    }
}
