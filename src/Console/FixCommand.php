<?php

namespace Realodix\Haiku\Console;

use Realodix\Haiku\App;
use Realodix\Haiku\Config\InvalidConfigurationException;
use Realodix\Haiku\Fixer\Fixer;
use Realodix\Haiku\Fixer\ValueObject\FixerRunContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fix',
    description: 'Sort, combine, and normalize ad-blocking filter lists.',
)]
class FixCommand extends Command
{
    public function __construct(
        private Fixer $fixer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'File or directory to process')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ignore the cache and process all files')
            ->addOption('backup', null, InputOption::VALUE_NONE, 'Create backup files before modifying')
            ->addOption('keep-empty-lines', null, InputOption::VALUE_NONE, 'Keep empty lines in output')
            ->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Path to config file')
            ->addOption('cache', null, InputOption::VALUE_OPTIONAL, 'Path to the cache file')
            ->addOption('x', null, InputOption::VALUE_NONE, 'Enable experimental features');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('config') && !file_exists($input->getOption('config'))) {
            throw new InvalidConfigurationException('The configuration file does not exist.');
        }

        $io = new SymfonyStyle($input, $output);
        $io->writeln(sprintf('%s <info>%s</info> by <comment>Realodix</comment>', App::NAME, App::version()));
        $io->newLine();

        // ---- Execute ----
        $startTime = microtime(true);
        $this->fixer->handle(
            new FixerRunContext(
                $input->getOption('force'),
                $input->getOption('backup'),
                $input->getOption('path'),
                $input->getOption('cache'),
                $input->getOption('config'),
                $input->getOption('keep-empty-lines'),
                $input->getOption('x'),
            ),
        );

        $stats = $this->fixer->stats();
        if ($stats->allSkipped()) {
            $io->writeln('<info>All files have been processed.</info>');
            $io->newLine();
        } else {
            $io->newLine();
            $io->writeln($stats);
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
