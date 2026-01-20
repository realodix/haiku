<?php

namespace Realodix\Haiku\Console;

use Realodix\Haiku\App;
use Realodix\Haiku\Config\InvalidConfigurationException;
use Realodix\Haiku\Enums\Mode;
use Realodix\Haiku\Fixer\Fixer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'fix',
    description: 'Process adblock filter files in a directory or single file.',
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
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force processing even if file has not changed')
            ->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Path to config file')
            ->addOption('cache', null, InputOption::VALUE_OPTIONAL, 'Path to the cache file');
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
            $this->inputMode($input),
            $input->getOption('path'),
            $input->getOption('cache'),
            $input->getOption('config'),
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

    protected function inputMode(InputInterface $input): Mode
    {
        return match (true) {
            $input->getOption('force') => Mode::Force,
            default => Mode::Default,
        };
    }
}
