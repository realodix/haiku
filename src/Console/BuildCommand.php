<?php

namespace Realodix\Haiku\Console;

use Realodix\Haiku\App;
use Realodix\Haiku\Builder\Builder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'build',
    description: 'Compiles multiple filter list sources into single unified output files.',
)]
class BuildCommand extends Command
{
    public function __construct(
        private Builder $builder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ignore cache and rebuild all sources')
            ->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Path to config file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->writeln(sprintf('%s <info>%s</info> by <comment>Realodix</comment>', App::NAME, App::version()));
        $io->newLine();

        // ---- Execute ----
        $startTime = microtime(true);
        $io->writeln('Building...');
        $this->builder->handle(
            new CommandOptions(
                configFile: $input->getOption('config'),
                ignoreCache: $input->getOption('force'),
            ),
        );

        if ($this->builder->stats()->allSkipped()) {
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
