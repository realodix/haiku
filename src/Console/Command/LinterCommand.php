<?php

namespace Realodix\Haiku\Console\Command;

use Realodix\Haiku\App;
use Realodix\Haiku\Config\InvalidConfigurationException;
use Realodix\Haiku\Console\CommandOptions;
use Realodix\Haiku\Linter\Linter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'lint',
    description: 'Analyses source code',
)]
class LinterCommand extends Command
{
    public function __construct(
        private Linter $linter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'File or directory to analyse')
            ->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Path to config file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $iConfig = $input->getOption('config');
        if ($iConfig && !file_exists($iConfig)) {
            throw new InvalidConfigurationException(sprintf('Cannot read config file "%s".', $iConfig));
        }

        $io = new SymfonyStyle($input, $output);
        $io->writeln(sprintf('%s <info>%s</info> by <comment>Realodix</comment>', App::NAME, App::version()));
        $io->newLine();

        /** @var \Symfony\Component\Console\Helper\ProgressBar|null $progressBar */
        $progressBar = null;

        $startTime = microtime(true);
        $errorReporter = $this->linter->run(
            new CommandOptions(
                configFile: $input->getOption('config'),
                path: $input->getOption('path'),
            ),
            function (int $count) use ($io, &$progressBar) {
                $progressBar = $io->createProgressBar($count);
                $progressBar->start();
            },
            function () use (&$progressBar) {
                $progressBar?->advance();
            },
        );

        if ($progressBar !== null) {
            $progressBar->finish();
            $io->newLine(2);
        }

        $this->renderErrors($io, $errorReporter);

        $io->writeln(sprintf(
            'Time: %s seconds, Memory: %s',
            round(microtime(true) - $startTime, 2),
            round(memory_get_peak_usage(true) / 1024 / 1024, 2).' MB',
        ));

        return $errorReporter->count() > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param \Realodix\Haiku\Linter\ErrorReporter $errorReporter
     */
    private function renderErrors(SymfonyStyle $io, $errorReporter): void
    {
        $errors = $errorReporter->getErrors();
        $globalErrors = $errorReporter->getGlobalErrors();

        if (empty($errors) && empty($globalErrors)) {
            $io->success('No errors found!');

            return;
        }

        foreach ($errors as $path => $issues) {
            usort($issues, fn($a, $b) => $a['line'] <=> $b['line']);

            $relativePath = Path::makeRelative($path, base_path());
            $io->writeln(' ------ ----------------------------------------------------------------------------------------------------');
            $io->writeln(sprintf('  Line   %s', $relativePath));
            $io->writeln(' ------ ----------------------------------------------------------------------------------------------------');

            foreach ($issues as $issue) {
                $io->writeln(sprintf('  :%-5d %s', $issue['line'], $issue['message']));

                if (isset($issue['tip'])) {
                    $io->writeln($this->meta($issue['tip'], '💡'));
                }

                if (isset($issue['ruleId'])) {
                    $io->writeln($this->meta($issue['ruleId']));
                }

                if (isset($issue['link'])) {
                    $io->writeln($this->meta("See: {$issue['link']}", '🔗'));
                }

                $io->writeln($this->meta("{$path}:{$issue['line']}", '✏️ '));
                $io->newLine();
            }
        }

        if (!empty($globalErrors)) {
            $io->writeln(' -- ----------------------------------------------------------------------------------------------------');
            $io->writeln('     Error');
            $io->writeln(' -- ----------------------------------------------------------------------------------------------------');
            foreach ($globalErrors as $error) {
                $io->writeln(sprintf('     %s', $error));
            }
            $io->newLine();
        }

        $io->error(sprintf('Found %d errors', $errorReporter->count()));
    }

    private function meta(string $content, ?string $icon = null): string
    {
        $iconPart = $icon ? "$icon " : '';

        return sprintf('         %s<fg=gray>%s</>', $iconPart, $content);
    }
}
